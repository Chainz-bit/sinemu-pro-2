<?php

namespace App\Services\Admin\LostItems;

use App\Http\Requests\Admin\LostItemIndexRequest;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class LostItemQueryService
{
    public function buildIndexQuery(LostItemIndexRequest $request): Builder
    {
        $selectColumns = [
            'laporan_barang_hilangs.id',
            'laporan_barang_hilangs.user_id',
            'laporan_barang_hilangs.nama_barang',
            'laporan_barang_hilangs.lokasi_hilang',
            'laporan_barang_hilangs.tanggal_hilang',
            'laporan_barang_hilangs.keterangan',
            'laporan_barang_hilangs.foto_barang',
            'laporan_barang_hilangs.created_at',
            'laporan_barang_hilangs.updated_at',
        ];
        if (Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
            $selectColumns[] = 'laporan_barang_hilangs.tampil_di_home';
        }
        if (Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
            $selectColumns[] = 'laporan_barang_hilangs.status_laporan';
        }

        $query = LaporanBarangHilang::query()
            ->with('user')
            ->select($selectColumns)
            ->selectSub(
                Klaim::query()
                    ->whereColumn('laporan_hilang_id', 'laporan_barang_hilangs.id')
                    ->latest('created_at')
                    ->limit(1)
                    ->select('status_klaim'),
                'latest_claim_status'
            );

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $query->where('laporan_barang_hilangs.sumber_laporan', 'lapor_hilang');
        }

        $this->applySearch($query, $request->search());
        $this->applyStatus($query, $request->status());
        $this->applyDate($query, $request->filterDate());
        $this->applySort($query, $request->sort());

        return $query;
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        $hasNamaColumn = Schema::hasColumn('users', 'nama');
        $hasNameColumn = Schema::hasColumn('users', 'name');

        $query->where(function ($builder) use ($search, $hasNamaColumn, $hasNameColumn): void {
            $builder->where('nama_barang', 'like', '%'.$search.'%')
                ->orWhere('lokasi_hilang', 'like', '%'.$search.'%');

            if (!$hasNamaColumn && !$hasNameColumn) {
                return;
            }

            $builder->orWhereHas('user', function ($userQuery) use ($search, $hasNamaColumn, $hasNameColumn): void {
                if ($hasNamaColumn) {
                    $userQuery->where('users.nama', 'like', '%'.$search.'%');
                }

                if ($hasNameColumn) {
                    if ($hasNamaColumn) {
                        $userQuery->orWhere('users.name', 'like', '%'.$search.'%');
                    } else {
                        $userQuery->where('users.name', 'like', '%'.$search.'%');
                    }
                }
            });
        });
    }

    private function applyStatus(Builder $query, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        if (Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
            $allowedStatuses = [
                WorkflowStatus::REPORT_SUBMITTED,
                WorkflowStatus::REPORT_APPROVED,
                WorkflowStatus::REPORT_REJECTED,
                WorkflowStatus::REPORT_MATCHED,
                WorkflowStatus::REPORT_CLAIMED,
                WorkflowStatus::REPORT_COMPLETED,
            ];

            if (in_array($status, $allowedStatuses, true)) {
                $query->where('laporan_barang_hilangs.status_laporan', $status);
            }

            return;
        }

        $legacyStatusMap = [
            WorkflowStatus::REPORT_SUBMITTED => WorkflowStatus::CLAIM_LEGACY_PENDING,
            WorkflowStatus::REPORT_REJECTED => WorkflowStatus::CLAIM_LEGACY_REJECTED,
            WorkflowStatus::REPORT_COMPLETED => WorkflowStatus::CLAIM_LEGACY_APPROVED,
        ];
        $claimStatus = $legacyStatusMap[$status] ?? null;
        if ($claimStatus === null) {
            return;
        }

        $query->whereExists(function ($claimQuery) use ($claimStatus): void {
            $claimQuery->selectRaw('1')
                ->from('klaims')
                ->whereColumn('klaims.laporan_hilang_id', 'laporan_barang_hilangs.id')
                ->where('klaims.status_klaim', $claimStatus);
        });
    }

    private function applyDate(Builder $query, ?string $date): void
    {
        if ($date !== null) {
            $query->whereDate('tanggal_hilang', $date);
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            LostItemIndexRequest::SORT_OLDEST => $query->orderBy('updated_at'),
            LostItemIndexRequest::SORT_NAME_ASC => $query->orderBy('nama_barang'),
            LostItemIndexRequest::SORT_NAME_DESC => $query->orderByDesc('nama_barang'),
            default => $query->orderByDesc('updated_at'),
        };
    }
}
