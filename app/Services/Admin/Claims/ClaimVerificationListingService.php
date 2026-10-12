<?php

namespace App\Services\Admin\Claims;

use App\Http\Requests\Admin\ClaimVerificationIndexRequest;
use App\Models\Admin;
use App\Models\Klaim;
use App\Support\ClaimStatusPresenter;
use App\Support\ManagerPortal;
use App\Support\WorkflowStatus;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimVerificationListingService
{
    /**
     * @return array{query:EloquentBuilder,sort:string}
     */
    public function prepareIndexQuery(ClaimVerificationIndexRequest $request): array
    {
        $pelaporSelect = 'COALESCE(users.nama, users.name, "Pengguna")';

        $query = Klaim::query()
            ->leftJoin('laporan_barang_hilangs', 'laporan_barang_hilangs.id', '=', 'klaims.laporan_hilang_id')
            ->leftJoin('barangs', 'barangs.id', '=', 'klaims.barang_id')
            ->leftJoin('users', 'users.id', '=', 'klaims.user_id')
            ->leftJoin('admins', 'admins.id', '=', 'klaims.admin_id')
            ->select([
                'klaims.id',
                'klaims.admin_id',
                'klaims.barang_id',
                'klaims.laporan_hilang_id',
                'klaims.status_klaim',
                DB::raw('COALESCE(klaims.status_verifikasi, "") as status_verifikasi'),
                'klaims.catatan',
                'klaims.created_at',
                'klaims.updated_at',
                DB::raw($pelaporSelect.' as pelapor_nama'),
                DB::raw('COALESCE(laporan_barang_hilangs.nama_barang, "-") as barang_hilang'),
                DB::raw('COALESCE(barangs.nama_barang, "-") as barang_temuan'),
                DB::raw('COALESCE(barangs.foto_barang, laporan_barang_hilangs.foto_barang, "") as foto_barang'),
                DB::raw('COALESCE(barangs.lokasi_ditemukan, "-") as lokasi'),
                DB::raw('COALESCE(barangs.status_barang, "-") as status_barang_temuan'),
                'barangs.region_id as barang_region_id',
                'laporan_barang_hilangs.region_id as laporan_region_id',
                DB::raw('COALESCE(admins.nama, "-") as admin_nama'),
                DB::raw('COALESCE(laporan_barang_hilangs.tampil_di_home, 0) as laporan_hilang_tampil_di_home'),
                DB::raw('COALESCE(barangs.tampil_di_home, 0) as barang_tampil_di_home'),
            ]);

        $sort = $request->sort();

        $this->applyManagerScope($query);
        $this->applySearch($query, $request->search());
        $this->applyStatus($query, $request->status());
        $this->applyDate($query, $request->filterDate());
        $this->applySort($query, $sort);

        return [
            'query' => $query,
            'sort' => $sort,
        ];
    }

    private function applyManagerScope(EloquentBuilder $query): void
    {
        /** @var Admin|null $admin */
        $admin = ManagerPortal::user();
        if (! $admin) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($scope) use ($admin): void {
            $scope->where('klaims.admin_id', $admin->id);

            if ($admin->region_id) {
                $scope->orWhere(function ($legacyScope) use ($admin): void {
                    $legacyScope->whereNull('klaims.admin_id')
                        ->where(function ($regionScope) use ($admin): void {
                            $regionScope
                                ->where('barangs.region_id', $admin->region_id)
                                ->orWhere('laporan_barang_hilangs.region_id', $admin->region_id);
                        });
                });
            }
        });
    }

    private function applySearch(EloquentBuilder $query, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        $trimmed = trim($search);
        $claimId = null;
        if (preg_match('/^#?KLM-?0*([1-9]\d*)$/i', $trimmed, $matches)) {
            $claimId = (int) $matches[1];
        } elseif (preg_match('/^0*([1-9]\d*)$/', $trimmed, $matches)) {
            $claimId = (int) $matches[1];
        }

        $query->where(function ($builder) use ($search, $claimId): void {
            $builder->where('laporan_barang_hilangs.nama_barang', 'like', '%'.$search.'%')
                ->orWhere('barangs.nama_barang', 'like', '%'.$search.'%');

            if ($claimId !== null) {
                $builder->orWhere('klaims.id', $claimId);
            }

            $builder->orWhere('users.nama', 'like', '%'.$search.'%')
                ->orWhere('users.name', 'like', '%'.$search.'%');
        });
    }

    private function applyStatus(EloquentBuilder $query, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        match ($status) {
            ClaimVerificationIndexRequest::STATUS_WAITING, WorkflowStatus::CLAIM_LEGACY_PENDING => $query->whereIn(
                'klaims.status_verifikasi',
                [WorkflowStatus::CLAIM_SUBMITTED, WorkflowStatus::CLAIM_UNDER_REVIEW]
            ),
            WorkflowStatus::CLAIM_LEGACY_APPROVED => $query->where('klaims.status_verifikasi', WorkflowStatus::CLAIM_APPROVED),
            WorkflowStatus::CLAIM_LEGACY_REJECTED => $query->where('klaims.status_verifikasi', WorkflowStatus::CLAIM_REJECTED),
            ClaimVerificationIndexRequest::STATUS_DONE => $query->where('klaims.status_verifikasi', WorkflowStatus::CLAIM_COMPLETED),
            default => null,
        };
    }

    private function applyDate(EloquentBuilder $query, ?string $date): void
    {
        if ($date !== null) {
            $query->whereDate('klaims.created_at', $date);
        }
    }

    private function applySort(EloquentBuilder $query, string $sort): void
    {
        match ($sort) {
            ClaimVerificationIndexRequest::SORT_OLDEST => $query->orderBy('klaims.updated_at'),
            default => $query->orderByDesc('klaims.updated_at'),
        };
    }

    public function exportCsv(EloquentBuilder $query): StreamedResponse
    {
        $exportClaims = $query->get();

        return new StreamedResponse(function () use ($exportClaims) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Pelapor', 'Barang Temuan', 'Barang Hilang', 'Lokasi', 'Status Klaim', 'Tanggal Klaim']);

            foreach ($exportClaims as $claim) {
                $statusKey = ClaimStatusPresenter::key(
                    statusKlaim: (string) $claim->status_klaim,
                    statusVerifikasi: (string) ($claim->status_verifikasi ?? ''),
                    statusBarang: (string) ($claim->status_barang_temuan ?? '')
                );
                fputcsv($handle, [
                    $claim->pelapor_nama,
                    $claim->barang_temuan,
                    $claim->barang_hilang,
                    $claim->lokasi,
                    ClaimStatusPresenter::label($statusKey),
                    $claim->created_at,
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="verifikasi-klaim.csv"',
        ]);
    }
}
