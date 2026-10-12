<?php

namespace App\Actions\Claims;

use App\Models\Klaim;
use App\Services\UserNotificationService;
use App\Support\ClaimVerificationConfig;
use App\Support\RoleLabels;
use App\Support\WorkflowStatus;

class RejectClaimAction
{
    /**
     * @param  array<string,mixed>  $validated
     */
    public function execute(Klaim $klaim, array $validated, int $adminId): void
    {
        $verification = $this->buildVerificationResult($validated);

        $payload = [
            'status_klaim' => 'ditolak',
            'admin_id' => $adminId,
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
            'hasil_checklist' => $verification['checklist'],
            'skor_validitas' => $verification['score'],
            'catatan_verifikasi_admin' => $validated['catatan_verifikasi_admin'] ?? null,
            'alasan_penolakan' => $validated['alasan_penolakan'],
            'diverifikasi_at' => now(),
        ];
        $klaim->update($payload);

        if ($klaim->barang && $klaim->barang->status_barang === WorkflowStatus::FOUND_CLAIM_IN_PROGRESS) {
            $klaim->barang->update(['status_barang' => WorkflowStatus::FOUND_AVAILABLE]);
        }
        if ($klaim->pencocokan) {
            $klaim->pencocokan->update(['status_pencocokan' => WorkflowStatus::MATCH_CLAIM_REJECTED]);
        }

        $this->notifyUser($klaim, 'klaim_ditolak', 'Klaim Ditolak', ucfirst(RoleLabels::managerLower()).' menolak klaim untuk ');
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array{score:int,checklist:array<string,bool>}
     */
    private function buildVerificationResult(array $validated): array
    {
        $checklist = [
            'identitas_pelapor_valid' => ($validated['identitas_pelapor_valid'] ?? 0) == 1,
            'detail_barang_valid' => ($validated['detail_barang_valid'] ?? 0) == 1,
            'kronologi_valid' => ($validated['kronologi_valid'] ?? 0) == 1,
            'bukti_visual_valid' => ($validated['bukti_visual_valid'] ?? 0) == 1,
            'kecocokan_data_laporan' => ($validated['kecocokan_data_laporan'] ?? 0) == 1,
        ];

        return [
            'score' => ClaimVerificationConfig::calculateScore($checklist),
            'checklist' => $checklist,
        ];
    }

    private function notifyUser(Klaim $klaim, string $type, string $title, string $prefixMessage): void
    {
        if (is_null($klaim->user_id)) {
            return;
        }

        $namaBarang = $klaim->barang?->nama_barang ?? $klaim->laporanHilang?->nama_barang ?? 'barang Anda';
        UserNotificationService::notifyUser(
            userId: (int) $klaim->user_id,
            type: $type,
            title: $title,
            message: $prefixMessage.$namaBarang.'.',
            actionUrl: route('user.claim-history'),
            meta: ['klaim_id' => $klaim->id]
        );
    }
}
