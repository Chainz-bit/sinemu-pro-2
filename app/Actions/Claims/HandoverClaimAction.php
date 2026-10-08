<?php

namespace App\Actions\Claims;

use App\Models\BarangStatusHistory;
use App\Models\Klaim;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Schema;

class HandoverClaimAction
{
    /**
     * @param array<string,mixed> $handoverData
     */
    public function execute(Klaim $klaim, int $adminId, array $handoverData = []): void
    {
        if (isset($handoverData['kode_tiket'])) {
            $expectedIdPad = str_pad((string) $klaim->id, 5, '0', STR_PAD_LEFT);
            $raw = strtoupper(trim((string) $handoverData['kode_tiket']));
            $stripped = ltrim($raw, '#');
            $valid = in_array($raw, ['#KLM-' . $expectedIdPad, 'KLM-' . $expectedIdPad, $expectedIdPad, (string) $klaim->id], true)
                || in_array($stripped, ['KLM-' . $expectedIdPad, $expectedIdPad], true);
            if (!$valid) {
                throw new \InvalidArgumentException('Kode tiket tidak valid. Pastikan kode sesuai dengan tiket yang dibawa penerima.');
            }
        }

        $payload = [
            'admin_id' => $adminId,
        ];

        if (Schema::hasColumn('klaims', 'status_verifikasi')) {
            $payload['status_verifikasi'] = WorkflowStatus::CLAIM_COMPLETED;
        }

        if (Schema::hasColumn('klaims', 'nama_penerima')) {
            if (!empty($handoverData['nama_penerima'])) {
                $payload['nama_penerima'] = trim((string) $handoverData['nama_penerima']);
            } elseif (empty($klaim->nama_penerima)) {
                $payload['nama_penerima'] = $klaim->user?->nama ?? $klaim->user?->name;
            }
        }

        if (Schema::hasColumn('klaims', 'nomor_identitas_penerima') && isset($handoverData['nomor_identitas_penerima'])) {
            $payload['nomor_identitas_penerima'] = trim((string) $handoverData['nomor_identitas_penerima']) ?: null;
        }

        if (Schema::hasColumn('klaims', 'catatan_serah_terima') && isset($handoverData['catatan_serah_terima'])) {
            $payload['catatan_serah_terima'] = trim((string) $handoverData['catatan_serah_terima']) ?: null;
        }

        if (Schema::hasColumn('klaims', 'foto_serah_terima') && !empty($handoverData['foto_serah_terima'])) {
            $payload['foto_serah_terima'] = $handoverData['foto_serah_terima'];
        }

        if (Schema::hasColumn('klaims', 'diserahkan_at')) {
            $payload['diserahkan_at'] = now();
        }

        $klaim->update($payload);

        if ($klaim->barang) {
            $oldStatus = (string) $klaim->barang->status_barang;
            $barangPayload = [
                'status_barang' => WorkflowStatus::FOUND_RETURNED,
            ];
            if (Schema::hasColumn('barangs', 'status_laporan')) {
                $barangPayload['status_laporan'] = WorkflowStatus::REPORT_COMPLETED;
            }
            if (Schema::hasColumn('barangs', 'tampil_di_home')) {
                $barangPayload['tampil_di_home'] = false;
            }
            $klaim->barang->update($barangPayload);

            $catatanHistory = 'Barang telah diserahterimakan kepada pemilik.';
            if (!empty($handoverData['catatan_serah_terima'])) {
                $catatanHistory .= ' Catatan: ' . $handoverData['catatan_serah_terima'];
            }

            if (class_exists(BarangStatusHistory::class) && Schema::hasTable('barang_status_histories')) {
                BarangStatusHistory::create([
                    'barang_id' => $klaim->barang->id,
                    'admin_id' => $adminId,
                    'status_lama' => $oldStatus,
                    'status_baru' => WorkflowStatus::FOUND_RETURNED,
                    'catatan' => $catatanHistory,
                ]);
            }
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
                message: 'Proses klaim ' . $namaBarang . ' telah selesai dan barang telah diserahterimakan kepada pemilik.',
                actionUrl: route('user.claim-history'),
                meta: ['klaim_id' => $klaim->id]
            );
        }
    }
}
