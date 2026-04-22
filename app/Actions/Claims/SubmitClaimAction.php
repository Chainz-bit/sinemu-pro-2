<?php

namespace App\Actions\Claims;

use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Support\WorkflowStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class SubmitClaimAction
{
    /**
     * @param array<string,mixed> $validated
     * @param array<int,UploadedFile> $photos
     * @return array{ok:bool,message:string}
     */
    public function execute(array $validated, array $photos = []): array
    {
        $user = Auth::user();
        if (!$user) {
            return ['ok' => false, 'message' => 'Anda harus login sebelum mengajukan klaim.'];
        }

        $barang = Barang::query()->select('id', 'admin_id', 'status_barang')->find($validated['barang_id']);
        if (!$barang) {
            return ['ok' => false, 'message' => 'Barang temuan tidak ditemukan.'];
        }

        $hasDuplicateClaim = Klaim::query()
            ->where('user_id', (int) Auth::id())
            ->where('barang_id', (int) $barang->id)
            ->whereIn('status_klaim', ['pending', 'disetujui'])
            ->exists();
        if ($hasDuplicateClaim) {
            return ['ok' => false, 'message' => 'Anda sudah pernah mengajukan klaim aktif untuk barang ini.'];
        }

        $laporan = LaporanBarangHilang::query()
            ->where('id', (int) $validated['laporan_hilang_id'])
            ->where('user_id', (int) Auth::id())
            ->first();

        if (!$laporan) {
            return ['ok' => false, 'message' => 'Pilih laporan barang hilang milik Anda yang valid sebelum mengajukan klaim.'];
        }

        if (!in_array((string) $laporan->status_laporan, [WorkflowStatus::REPORT_APPROVED, WorkflowStatus::REPORT_MATCHED, WorkflowStatus::REPORT_CLAIMED], true)) {
            return ['ok' => false, 'message' => 'Laporan barang hilang harus disetujui admin terlebih dahulu sebelum klaim.'];
        }

        $pencocokan = Pencocokan::query()
            ->where('laporan_hilang_id', (int) $laporan->id)
            ->where('barang_id', (int) $barang->id)
            ->whereIn('status_pencocokan', [WorkflowStatus::MATCH_CONFIRMED, WorkflowStatus::MATCH_CLAIM_IN_PROGRESS, WorkflowStatus::MATCH_CLAIM_REJECTED])
            ->latest('updated_at')
            ->first();

        if (!$pencocokan) {
            return ['ok' => false, 'message' => 'Barang ini belum ditandai cocok oleh admin dengan laporan Anda.'];
        }

        $hasBlockingClaimForReport = Klaim::query()
            ->where('laporan_hilang_id', (int) $laporan->id)
            ->whereIn('status_klaim', ['pending', 'disetujui'])
            ->exists();
        if ($hasBlockingClaimForReport) {
            return ['ok' => false, 'message' => 'Laporan ini masih punya klaim aktif. Tunggu proses klaim sebelumnya selesai.'];
        }

        $laporanUpdatePayload = [];
        if (empty($laporan->kontak_pelapor) && !empty($validated['kontak_pelapor'])) {
            $laporanUpdatePayload['kontak_pelapor'] = $validated['kontak_pelapor'];
        }
        if (empty($laporan->bukti_kepemilikan) && !empty($validated['bukti_kepemilikan'])) {
            $laporanUpdatePayload['bukti_kepemilikan'] = $validated['bukti_kepemilikan'];
        }
        if ($laporanUpdatePayload !== []) {
            $laporan->update($laporanUpdatePayload);
        }

        $buktiFotoPaths = [];
        foreach ($photos as $photo) {
            $buktiFotoPaths[] = $photo->store('verifikasi-klaim/' . now()->format('Y/m'), 'public');
        }

        Klaim::create([
            'laporan_hilang_id' => (int) $laporan->id,
            'barang_id' => (int) $barang->id,
            'pencocokan_id' => (int) $pencocokan->id,
            'user_id' => (int) Auth::id(),
            'admin_id' => (int) $barang->admin_id,
            'status_klaim' => 'pending',
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => $validated['catatan'] ?? null,
            'bukti_foto' => $buktiFotoPaths,
            'bukti_ciri_khusus' => $validated['bukti_ciri_khusus'],
            'bukti_detail_isi' => $validated['bukti_detail_isi'] ?? null,
            'bukti_lokasi_spesifik' => $validated['bukti_lokasi_spesifik'],
            'bukti_waktu_hilang' => $this->normalizeClaimTime((string) $validated['bukti_waktu_hilang']),
        ]);

        if ($barang->status_barang === 'tersedia') {
            $barang->update(['status_barang' => 'dalam_proses_klaim']);
        }
        $laporan->update(['status_laporan' => WorkflowStatus::REPORT_CLAIMED]);
        $pencocokan->update(['status_pencocokan' => WorkflowStatus::MATCH_CLAIM_IN_PROGRESS]);

        return ['ok' => true, 'message' => 'Pengajuan klaim berhasil dikirim. Pantau status verifikasi di Riwayat Klaim.'];
    }

    private function normalizeClaimTime(string $rawTime): string
    {
        $rawTime = trim($rawTime);
        if ($rawTime === '') {
            return $rawTime;
        }

        $segments = explode(':', $rawTime);
        $hour = isset($segments[0]) ? (int) $segments[0] : 0;
        $minute = isset($segments[1]) ? (int) $segments[1] : 0;

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
