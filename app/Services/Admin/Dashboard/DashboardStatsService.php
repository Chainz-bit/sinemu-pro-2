<?php

namespace App\Services\Admin\Dashboard;

use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Schema;

class DashboardStatsService
{
    /**
     * @return array{totalHilang:int,totalTemuan:int,menungguVerifikasi:int}
     */
    public function build(): array
    {
        $totalHilangQuery = LaporanBarangHilang::query();
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $totalHilangQuery->where('sumber_laporan', 'lapor_hilang');
        }

        $menungguVerifikasi = Schema::hasColumn('klaims', 'status_verifikasi')
            ? Klaim::query()
                ->whereIn('status_verifikasi', [WorkflowStatus::CLAIM_SUBMITTED, WorkflowStatus::CLAIM_UNDER_REVIEW])
                ->count()
            : Klaim::where('status_klaim', WorkflowStatus::CLAIM_LEGACY_PENDING)->count();

        return [
            'totalHilang' => $totalHilangQuery->count(),
            'totalTemuan' => Barang::count(),
            'menungguVerifikasi' => $menungguVerifikasi,
        ];
    }
}
