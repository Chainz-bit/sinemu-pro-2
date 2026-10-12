<?php

namespace App\Services\Admin\Dashboard;

use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\ManagerPortal;
use App\Support\WorkflowStatus;

class DashboardStatsService
{
    /**
     * @return array{totalHilang:int,totalTemuan:int,menungguVerifikasi:int}
     */
    public function build(): array
    {
        $admin = ManagerPortal::user();
        if (! $admin || empty($admin->region_id)) {
            return [
                'totalHilang' => 0,
                'totalTemuan' => 0,
                'menungguVerifikasi' => 0,
            ];
        }

        $totalHilangQuery = LaporanBarangHilang::query()
            ->where('sumber_laporan', 'lapor_hilang')
            ->where('region_id', $admin->region_id);

        $menungguVerifikasi = Klaim::query()
            ->leftJoin('barangs', 'barangs.id', '=', 'klaims.barang_id')
            ->leftJoin('laporan_barang_hilangs', 'laporan_barang_hilangs.id', '=', 'klaims.laporan_hilang_id')
            ->whereIn('status_verifikasi', [WorkflowStatus::CLAIM_SUBMITTED, WorkflowStatus::CLAIM_UNDER_REVIEW])
            ->where(function ($query) use ($admin): void {
                $query
                    ->where('barangs.region_id', $admin->region_id)
                    ->orWhere('laporan_barang_hilangs.region_id', $admin->region_id);
            })
            ->count();

        $totalTemuanQuery = Barang::query()
            ->where('region_id', $admin->region_id);

        return [
            'totalHilang' => $totalHilangQuery->count(),
            'totalTemuan' => $totalTemuanQuery->count(),
            'menungguVerifikasi' => $menungguVerifikasi,
        ];
    }
}
