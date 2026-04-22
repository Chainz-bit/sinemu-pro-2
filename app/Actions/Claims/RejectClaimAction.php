<?php

namespace App\Actions\Claims;

use App\Models\Klaim;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Schema;

class RejectClaimAction
{
    /**
     * @param array<string,mixed> $validated
     */
    public function execute(Klaim $klaim, array $validated, int $adminId): void
    {
        $verification = $this->buildVerificationResult($validated);

        $payload = [
            'status_klaim' => 'ditolak',
            'admin_id' => $adminId,
        ];
        if (Schema::hasColumn('klaims', 'status_verifikasi')) {
            $payload['status_verifikasi'] = WorkflowStatus::CLAIM_REJECTED;
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
            $payload['alasan_penolakan'] = $validated['alasan_penolakan'];
        }
        if (Schema::hasColumn('klaims', 'diverifikasi_at')) {
            $payload['diverifikasi_at'] = now();
        }
        $klaim->update($payload);

        if ($klaim->barang && $klaim->barang->status_barang === 'dalam_proses_klaim') {
            $klaim->barang->update(['status_barang' => 'tersedia']);
        }
        if ($klaim->pencocokan) {
            $klaim->pencocokan->update(['status_pencocokan' => WorkflowStatus::MATCH_CLAIM_REJECTED]);
        }

        $this->notifyUser($klaim, 'klaim_ditolak', 'Klaim Ditolak', 'Admin menolak klaim untuk ');
    }

    /**
     * @param array<string,mixed> $validated
     * @return array{score:int,checklist:array<string,bool>}
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

        return [
            'score' => $score,
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
            message: $prefixMessage . $namaBarang . '.',
            actionUrl: route('user.claim-history'),
            meta: ['klaim_id' => $klaim->id]
        );
    }
}
