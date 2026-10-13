<?php

namespace App\Actions\Claims;

use App\Models\Klaim;
use App\Services\UserNotificationService;
use App\Support\ClaimVerificationConfig;
use App\Support\RoleLabels;
use App\Support\WorkflowStatus;

class ApproveClaimAction
{
    /**
     * @param  array<string,mixed>  $validated
     */
    public function execute(Klaim $klaim, array $validated, int $adminId): bool
    {
        $verification = $this->buildVerificationResult($validated);
        if (! $verification['can_approve']) {
            return false;
        }

        $payload = [
            'status_klaim' => 'disetujui',
            'admin_id' => $adminId,
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
            'hasil_checklist' => $verification['checklist'],
            'skor_validitas' => $verification['score'],
            'catatan_verifikasi_admin' => $validated['catatan_verifikasi_admin'] ?? null,
            'alasan_penolakan' => null,
            'diverifikasi_at' => now(),
        ];
        $klaim->update($payload);

        if ($klaim->barang) {
            $klaim->barang->update(['status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS]);
        }
        if ($klaim->pencocokan) {
            $klaim->pencocokan->update(['status_pencocokan' => WorkflowStatus::MATCH_CLAIM_APPROVED]);
        }

        $this->notifyUser($klaim, 'klaim_disetujui', 'Klaim Disetujui', ucfirst(RoleLabels::managerLower()).' menyetujui klaim untuk ');

        return true;
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array{score:int,checklist:array<string,bool>,can_approve:bool}
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

        $score = ClaimVerificationConfig::calculateScore($checklist);
        $criticalChecksPassed = ClaimVerificationConfig::areCriticalChecksPassed($checklist);

        return [
            'score' => $score,
            'checklist' => $checklist,
            'can_approve' => $criticalChecksPassed && ClaimVerificationConfig::isScoreSufficient($score),
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
