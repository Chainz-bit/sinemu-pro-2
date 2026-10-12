<?php

namespace App\Actions\Claims;

use App\Models\BarangStatusHistory;
use App\Models\Klaim;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;

class HandoverClaimAction
{
    /**
     * @param  array<string,mixed>  $handoverData
     */
    public function execute(Klaim $klaim, int $adminId, array $handoverData = []): void
    {
        if (isset($handoverData['kode_tiket'])) {
            $expectedIdPad = str_pad((string) $klaim->id, 5, '0', STR_PAD_LEFT);
            $raw = strtoupper(trim((string) $handoverData['kode_tiket']));
            $stripped = ltrim($raw, '#');
            $valid = in_array($raw, ['#KLM-'.$expectedIdPad, 'KLM-'.$expectedIdPad, $expectedIdPad, (string) $klaim->id], true)
                || in_array($stripped, ['KLM-'.$expectedIdPad, $expectedIdPad], true);
            if (! $valid) {
                throw new \InvalidArgumentException('Kode tiket tidak valid. Pastikan kode sesuai dengan tiket yang dibawa penerima.');
            }
        }

        $payload = [
            'admin_id' => $adminId,
            'status_verifikasi' => WorkflowStatus::CLAIM_COMPLETED,
            'diserahkan_at' => now(),
        ];

        if (! empty($handoverData['nama_penerima'])) {
            $payload['nama_penerima'] = trim((string) $handoverData['nama_penerima']);
        } elseif (empty($klaim->nama_penerima)) {
            $payload['nama_penerima'] = $klaim->user?->nama ?? $klaim->user?->name;
        }

        if (isset($handoverData['nomor_identitas_penerima'])) {
            $payload['nomor_identitas_penerima'] = trim((string) $handoverData['nomor_identitas_penerima']) ?: null;
        }

        if (isset($handoverData['catatan_serah_terima'])) {
            $payload['catatan_serah_terima'] = trim((string) $handoverData['catatan_serah_terima']) ?: null;
        }

        if (! empty($handoverData['foto_serah_terima'])) {
            $payload['foto_serah_terima'] = $handoverData['foto_serah_terima'];
        }

        $klaim->update($payload);

        if ($klaim->barang) {
            $oldStatus = (string) $klaim->barang->status_barang;
            $klaim->barang->update([
                'status_barang' => WorkflowStatus::FOUND_RETURNED,
                'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
                'tampil_di_home' => false,
            ]);

            $catatanHistory = 'Barang telah diserahterimakan kepada pemilik.';
            if (! empty($handoverData['catatan_serah_terima'])) {
                $catatanHistory .= ' Catatan: '.$handoverData['catatan_serah_terima'];
            }

            BarangStatusHistory::create([
                'barang_id' => $klaim->barang->id,
                'admin_id' => $adminId,
                'status_lama' => $oldStatus,
                'status_baru' => WorkflowStatus::FOUND_RETURNED,
                'catatan' => $catatanHistory,
            ]);
        }

        if ($klaim->laporanHilang) {
            $klaim->laporanHilang->update([
                'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
                'tampil_di_home' => false,
            ]);
        }

        if ($klaim->pencocokan) {
            $klaim->pencocokan->update(['status_pencocokan' => WorkflowStatus::MATCH_COMPLETED]);
        }

        if (! is_null($klaim->user_id)) {
            $namaBarang = $klaim->barang?->nama_barang ?? $klaim->laporanHilang?->nama_barang ?? 'barang Anda';
            UserNotificationService::notifyUser(
                userId: (int) $klaim->user_id,
                type: 'klaim_selesai',
                title: 'Barang Sudah Diserahkan',
                message: 'Proses klaim '.$namaBarang.' telah selesai dan barang telah diserahterimakan kepada pemilik.',
                actionUrl: route('user.claim-history'),
                meta: ['klaim_id' => $klaim->id]
            );
        }
    }
}
