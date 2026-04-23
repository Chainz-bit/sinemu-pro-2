<?php

namespace App\Services\Home;

use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Collection;

class HomeClaimableLostReportService
{
    /**
     * @return Collection<int,LaporanBarangHilang>
     */
    public function build(int $userId, callable $safeDatabaseCall, callable $hasDatabaseColumn): Collection
    {
        if ($userId <= 0) {
            return collect();
        }

        return $safeDatabaseCall(function () use ($userId, $hasDatabaseColumn) {
            $query = LaporanBarangHilang::query()
                ->where('user_id', $userId)
                ->select([
                    'id',
                    'nama_barang',
                    'lokasi_hilang',
                    'tanggal_hilang',
                    'kontak_pelapor',
                    'bukti_kepemilikan',
                ]);

            if ($hasDatabaseColumn('laporan_barang_hilangs', 'sumber_laporan')) {
                $query->where('sumber_laporan', 'lapor_hilang');
            }
            if ($hasDatabaseColumn('laporan_barang_hilangs', 'status_laporan')) {
                $query->whereIn('status_laporan', [
                    WorkflowStatus::REPORT_APPROVED,
                    WorkflowStatus::REPORT_MATCHED,
                    WorkflowStatus::REPORT_CLAIMED,
                ]);
            }

            return $query
                ->orderByDesc('tanggal_hilang')
                ->orderByDesc('updated_at')
                ->get();
        }, collect());
    }
}
