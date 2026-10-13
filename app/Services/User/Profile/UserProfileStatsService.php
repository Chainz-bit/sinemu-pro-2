<?php

namespace App\Services\User\Profile;

use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;

class UserProfileStatsService
{
    /**
     * @return array{laporanDiajukan: int, klaimMenunggu: int, klaimSelesai: int}
     */
    public function build(int $userId): array
    {
        $laporanDiajukan = LaporanBarangHilang::query()
            ->where('user_id', $userId)
            ->where('sumber_laporan', 'lapor_hilang')
            ->count();

        $klaimMenunggu = Klaim::query()
            ->where('user_id', $userId)
            ->whereIn('status_verifikasi', [
                WorkflowStatus::CLAIM_SUBMITTED,
                WorkflowStatus::CLAIM_UNDER_REVIEW,
            ])
            ->count();

        $klaimSelesai = Klaim::query()
            ->where('user_id', $userId)
            ->whereIn('status_verifikasi', [
                WorkflowStatus::CLAIM_APPROVED,
                WorkflowStatus::CLAIM_REJECTED,
                WorkflowStatus::CLAIM_COMPLETED,
            ])
            ->count();

        return [
            'laporanDiajukan' => $laporanDiajukan,
            'klaimMenunggu' => $klaimMenunggu,
            'klaimSelesai' => $klaimSelesai,
        ];
    }
}
