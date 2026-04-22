<?php

namespace App\Actions\Claims;

use App\Models\Klaim;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Schema;

class ApproveClaimAction
{
    /**
     * @param array<string,mixed> $validated
     */
    public function execute(Klaim $klaim, array $validated, int $adminId): bool
    {
        $verification = $this->buildVerificationResult($validated);
        if (!$verification['can_approve']) {
            return false;
        }

        $payload = [
            'status_klaim' => 'disetujui',
            'admin_id' => $adminId,
        ];
        if (Schema::hasColumn('klaims', 'status_verifikasi')) {
            $payload['status_verifikasi'] = WorkflowStatus::CLAIM_APPROVED;
        }
        if (Schema::hasColumn('klaims', 'hasil_checklist')) {
            $payload['hasil_checklist'] = $verification['checklist'];
        }
        if (Schema::hasColumn('klaims', 'skor_validitas')) {
            $payload['skor_validitas'] = $verification['score'];
        }
        if (Schema::hasColumn('klaims', 'catatan_verifikasi_admin')) {
            $payload['catatan_verifikasi_admin'] = $validated['catatan_verifikasi_admin'] ?? null;
        }
        if (Schema::hasColumn('klaims', 'alasan_penolakan')) {
            $payload['alasan_penolakan'] = null;
        }
        if (Schema::hasColumn('klaims', 'diverifikasi_at')) {
            $payload['diverifikasi_at'] = now();
        }
        $klaim->update($payload);

        if ($klaim->barang) {
            $klaim->barang->update(['status_barang' => 'sudah_diklaim']);
        }
        if ($klaim->pencocokan) {
            $klaim->pencocokan->update(['status_pencocokan' => WorkflowStatus::MATCH_CLAIM_APPROVED]);
        }

        $this->notifyUser($klaim, 'klaim_disetujui', 'Klaim Disetujui', 'Admin menyetujui klaim untuk ');

        return true;
    }

    /**
     * @param array<string,mixed> $validated
     * @return array{score:int,checklist:array<string,bool>,can_approve:bool}
     */
    private function buildVerificationResult(array $validated): array
    {
        $checklist = [
            'identitas_pelapor_valid' => ((string) ($validated['identitas_pelapor_valid'] ?? '0')) === '1',
            'detail_barang_valid' => ((string) ($validated['detail_barang_valid'] ?? '0')) === '1',
            'kronologi_valid' => ((string) ($validated['kronologi_valid'] ?? '0')) === '1',
            'bukti_visual_valid' => ((string) ($validated['bukti_visual_valid'] ?? '0')) === '1',
            'kecocokan_data_laporan' => ((string) ($validated['kecocokan_data_laporan'] ?? '0')) === '1',
        ];

        $weights = [
            'identitas_pelapor_valid' => 20,
            'detail_barang_valid' => 25,
            'kronologi_valid' => 20,
            'bukti_visual_valid' => 20,
            'kecocokan_data_laporan' => 15,
        ];

        $score = 0;
        foreach ($weights as $key => $weight) {
            if (($checklist[$key] ?? false) === true) {
                $score += $weight;
            }
        }

        $criticalChecksPassed =
            ($checklist['detail_barang_valid'] ?? false)
            && ($checklist['kronologi_valid'] ?? false)
            && ($checklist['bukti_visual_valid'] ?? false);

        return [
            'score' => $score,
            'checklist' => $checklist,
            'can_approve' => $criticalChecksPassed && $score >= 75,
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
            message: $prefixMessage . $namaBarang . '.',
            actionUrl: route('user.claim-history'),
            meta: ['klaim_id' => $klaim->id]
        );
    }
}
