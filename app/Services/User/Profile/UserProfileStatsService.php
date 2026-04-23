<?php

namespace App\Services\User\Profile;

use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Schema;

class UserProfileStatsService
{
    /**
     * @return array{laporanDiajukan: int, klaimMenunggu: int, klaimSelesai: int}
     */
    public function build(int $userId): array
    {
        $laporanDiajukanQuery = LaporanBarangHilang::query()->where('user_id', $userId);
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $laporanDiajukanQuery->where('sumber_laporan', 'lapor_hilang');
        }

        $laporanDiajukan = (clone $laporanDiajukanQuery)->count();
        $hasClaimVerification = Schema::hasColumn('klaims', 'status_verifikasi');

        $klaimMenungguQuery = Klaim::query()->where('user_id', $userId);
        if ($hasClaimVerification) {
            $klaimMenungguQuery->whereIn('status_verifikasi', [
                WorkflowStatus::CLAIM_SUBMITTED,
                WorkflowStatus::CLAIM_UNDER_REVIEW,
            ]);
        } else {
            $klaimMenungguQuery->where('status_klaim', 'pending');
        }

        $klaimSelesaiQuery = Klaim::query()->where('user_id', $userId);
        if ($hasClaimVerification) {
            $klaimSelesaiQuery->whereIn('status_verifikasi', [
                WorkflowStatus::CLAIM_APPROVED,
                WorkflowStatus::CLAIM_REJECTED,
                WorkflowStatus::CLAIM_COMPLETED,
            ]);
        } else {
            $klaimSelesaiQuery->whereIn('status_klaim', ['disetujui', 'ditolak']);
        }

        return [
            'laporanDiajukan' => $laporanDiajukan,
            'klaimMenunggu' => $klaimMenungguQuery->count(),
            'klaimSelesai' => $klaimSelesaiQuery->count(),
        ];
    }
}
