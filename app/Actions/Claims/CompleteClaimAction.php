<?php

namespace App\Actions\Claims;

use App\Models\Klaim;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Schema;

class CompleteClaimAction
{
    public function execute(Klaim $klaim, int $adminId): void
    {
        $payload = [
            'admin_id' => $adminId,
        ];
        if (Schema::hasColumn('klaims', 'status_verifikasi')) {
            $payload['status_verifikasi'] = WorkflowStatus::CLAIM_COMPLETED;
        }
        $klaim->update($payload);

        if ($klaim->barang) {
            $barangPayload = [
                'status_barang' => 'sudah_dikembalikan',
            ];
            if (Schema::hasColumn('barangs', 'status_laporan')) {
                $barangPayload['status_laporan'] = WorkflowStatus::REPORT_COMPLETED;
            }
            if (Schema::hasColumn('barangs', 'tampil_di_home')) {
                $barangPayload['tampil_di_home'] = false;
            }
            $klaim->barang->update($barangPayload);
        }

        if ($klaim->laporanHilang) {
            $lostPayload = [];
            if (Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
                $lostPayload['status_laporan'] = WorkflowStatus::REPORT_COMPLETED;
            }
            if (Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
                $lostPayload['tampil_di_home'] = false;
            }
            if ($lostPayload !== []) {
                $klaim->laporanHilang->update($lostPayload);
            }
        }

        if ($klaim->pencocokan) {
            $klaim->pencocokan->update(['status_pencocokan' => WorkflowStatus::MATCH_COMPLETED]);
        }

        if (!is_null($klaim->user_id)) {
            $namaBarang = $klaim->barang?->nama_barang ?? $klaim->laporanHilang?->nama_barang ?? 'barang Anda';
            UserNotificationService::notifyUser(
                userId: (int) $klaim->user_id,
                type: 'klaim_selesai',
                title: 'Barang Sudah Diserahkan',
                message: 'Proses klaim ' . $namaBarang . ' telah selesai dan barang dinyatakan dikembalikan.',
                actionUrl: route('user.claim-history'),
                meta: ['klaim_id' => $klaim->id]
            );
        }
    }
}
