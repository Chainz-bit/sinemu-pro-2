<?php

namespace App\Services\User;

use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Schema;

class UserDashboardStatsService
{
    /**
     * @return array{totalLaporHilang:int,totalPengajuanKlaim:int,menungguVerifikasi:int}
     */
    public function build(int $userId, bool $hasSourceColumn, bool $hasClaimVerificationColumn): array
    {
        $lostReportsQuery = LaporanBarangHilang::query()->where('user_id', $userId);
        if ($hasSourceColumn) {
            $lostReportsQuery->where('sumber_laporan', 'lapor_hilang');
        }

        $menungguQuery = Klaim::query()->where('user_id', $userId);
        if ($hasClaimVerificationColumn) {
            $menungguQuery->whereIn('status_verifikasi', [WorkflowStatus::CLAIM_SUBMITTED, WorkflowStatus::CLAIM_UNDER_REVIEW]);
        } else {
            $menungguQuery->where('status_klaim', 'pending');
        }

        return [
            'totalLaporHilang' => (clone $lostReportsQuery)->count(),
            'totalPengajuanKlaim' => Klaim::query()->where('user_id', $userId)->count(),
            'menungguVerifikasi' => $menungguQuery->count(),
        ];
    }
}
