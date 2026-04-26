<?php

namespace App\Services\Admin\Claims;

use App\Http\Requests\Admin\ClaimVerificationIndexRequest;
use App\Models\Klaim;
use App\Support\ClaimStatusPresenter;
use App\Support\WorkflowStatus;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimVerificationListingService
{
    /**
     * @return array{query:EloquentBuilder,sort:string}
     */
    public function prepareIndexQuery(ClaimVerificationIndexRequest $request): array
    {
        $hasNamaColumn = Schema::hasColumn('users', 'nama');
        $hasNameColumn = Schema::hasColumn('users', 'name');
        $hasLostHomeFlag = Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home');
        $hasFoundHomeFlag = Schema::hasColumn('barangs', 'tampil_di_home');
        $hasClaimVerificationStatus = Schema::hasColumn('klaims', 'status_verifikasi');

        $pelaporSelect = '"Pengguna"';
        if ($hasNamaColumn && $hasNameColumn) {
            $pelaporSelect = 'COALESCE(users.nama, users.name, "Pengguna")';
        } elseif ($hasNamaColumn) {
            $pelaporSelect = 'COALESCE(users.nama, "Pengguna")';
        } elseif ($hasNameColumn) {
            $pelaporSelect = 'COALESCE(users.name, "Pengguna")';
        }

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
                DB::raw(($hasClaimVerificationStatus ? 'COALESCE(klaims.status_verifikasi, "")' : '""') . ' as status_verifikasi'),
                'klaims.catatan',
                'klaims.created_at',
                'klaims.updated_at',
                DB::raw($pelaporSelect . ' as pelapor_nama'),
                DB::raw('COALESCE(laporan_barang_hilangs.nama_barang, "-") as barang_hilang'),
                DB::raw('COALESCE(barangs.nama_barang, "-") as barang_temuan'),
                DB::raw('COALESCE(barangs.foto_barang, laporan_barang_hilangs.foto_barang, "") as foto_barang'),
                DB::raw('COALESCE(barangs.lokasi_ditemukan, "-") as lokasi'),
                DB::raw('COALESCE(barangs.status_barang, "-") as status_barang_temuan'),
                DB::raw('COALESCE(admins.nama, "-") as admin_nama'),
                DB::raw(($hasLostHomeFlag ? 'COALESCE(laporan_barang_hilangs.tampil_di_home, 0)' : '0') . ' as laporan_hilang_tampil_di_home'),
                DB::raw(($hasFoundHomeFlag ? 'COALESCE(barangs.tampil_di_home, 0)' : '0') . ' as barang_tampil_di_home'),
            ]);

        $sort = $request->sort();

        $this->applySearch($query, $request->search(), $hasNamaColumn, $hasNameColumn);
        $this->applyStatus($query, $request->status(), $hasClaimVerificationStatus);
        $this->applyDate($query, $request->filterDate());
        $this->applySort($query, $sort);

        return [
            'query' => $query,
            'sort' => $sort,
        ];
    }

    private function applySearch(EloquentBuilder $query, ?string $search, bool $hasNamaColumn, bool $hasNameColumn): void
    {
        if ($search === null) {
            return;
        }

        $query->where(function ($builder) use ($search, $hasNamaColumn, $hasNameColumn): void {
            $builder->where('laporan_barang_hilangs.nama_barang', 'like', '%' . $search . '%')
                ->orWhere('barangs.nama_barang', 'like', '%' . $search . '%');

            if ($hasNamaColumn) {
                $builder->orWhere('users.nama', 'like', '%' . $search . '%');
            }

            if ($hasNameColumn) {
                $builder->orWhere('users.name', 'like', '%' . $search . '%');
            }
        });
    }

    private function applyStatus(EloquentBuilder $query, ?string $status, bool $hasClaimVerificationStatus): void
    {
        if ($status === null) {
            return;
        }

        if ($hasClaimVerificationStatus) {
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

            return;
        }

        match ($status) {
            ClaimVerificationIndexRequest::STATUS_WAITING, WorkflowStatus::CLAIM_LEGACY_PENDING => $query->where('klaims.status_klaim', WorkflowStatus::CLAIM_LEGACY_PENDING),
            ClaimVerificationIndexRequest::STATUS_DONE => $query->where('klaims.status_klaim', WorkflowStatus::CLAIM_LEGACY_APPROVED)
                ->where('barangs.status_barang', WorkflowStatus::FOUND_RETURNED),
            WorkflowStatus::CLAIM_LEGACY_APPROVED, WorkflowStatus::CLAIM_LEGACY_REJECTED => $query->where('klaims.status_klaim', $status),
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
