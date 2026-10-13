<?php

namespace App\Services\Admin\LostItems;

use App\Http\Requests\Admin\LostItemIndexRequest;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\ManagerPortal;
use App\Support\WorkflowStatus;
use Illuminate\Database\Eloquent\Builder;

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
            'laporan_barang_hilangs.tampil_di_home',
            'laporan_barang_hilangs.status_laporan',
        ];

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
            )
            ->withCount(['klaims', 'pencocokans']);

        $query->where('laporan_barang_hilangs.sumber_laporan', 'lapor_hilang');
        $admin = ManagerPortal::user();
        if ($admin && $admin->region_id) {
            $query->where('laporan_barang_hilangs.region_id', $admin->region_id);
        } else {
            $query->whereRaw('1 = 0');
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

        $query->where(function ($builder) use ($search): void {
            $builder->where('nama_barang', 'like', '%'.$search.'%')
                ->orWhere('lokasi_hilang', 'like', '%'.$search.'%')
                ->orWhereHas('user', function ($userQuery) use ($search): void {
                    $userQuery->where('users.nama', 'like', '%'.$search.'%')
                        ->orWhere('users.name', 'like', '%'.$search.'%');
                });
        });
    }

    private function applyStatus(Builder $query, ?string $status): void
    {
        if ($status === null) {
            return;
        }

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
