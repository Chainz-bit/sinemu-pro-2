<?php

namespace App\Actions\Claims;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\User;
use App\Support\WorkflowStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SubmitClaimAction
{
    /**
     * @param array<string,mixed> $validated
     * @param array<int,UploadedFile> $photos
     * @return array{ok:bool,message:string,status?:int,claim?:Klaim}
     */
    public function execute(array $validated, array $photos = [], ?User $user = null): array
    {
        $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();

        $user ??= Auth::user();
        if (!$user) {
            return ['ok' => false, 'message' => 'Anda harus login sebelum mengajukan klaim.', 'status' => 401];
        }

        $buktiFotoPaths = [];
        try {
            return DB::transaction(function () use ($validated, $photos, $user, $managerRoleLabelLower, &$buktiFotoPaths): array {
                $laporanId = $this->positiveIntegerOrNull($validated['laporan_hilang_id'] ?? null);
                $laporan = null;
                $pencocokan = null;

                if ($laporanId !== null) {
                    $laporan = LaporanBarangHilang::query()
                        ->where('id', $laporanId)
                        ->where('user_id', (int) $user->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$laporan) {
                        return ['ok' => false, 'message' => 'Pilih laporan barang hilang milik Anda yang valid sebelum mengajukan klaim.', 'status' => 422];
                    }
                }

                $barang = Barang::query()
                    ->select('id', 'admin_id', 'region_id', 'user_id', 'status_barang', 'status_laporan')
                    ->whereKey((int) $validated['barang_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$barang) {
                    return ['ok' => false, 'message' => 'Barang temuan tidak ditemukan.', 'status' => 404];
                }

                if (!is_null($barang->user_id) && (int) $barang->user_id === (int) $user->id) {
                    return ['ok' => false, 'message' => 'Tidak bisa mengklaim barang temuan milik sendiri.', 'status' => 403];
                }

                if (Klaim::query()
                    ->where('barang_id', (int) $barang->id)
                    ->where('status_verifikasi', WorkflowStatus::CLAIM_COMPLETED)
                    ->exists()) {
                    return ['ok' => false, 'message' => 'Barang ini sudah selesai diklaim dan tidak dapat diajukan ulang.', 'status' => 409];
                }

                if ((string) $barang->status_barang !== WorkflowStatus::FOUND_AVAILABLE) {
                    return ['ok' => false, 'message' => 'Barang ini sedang tidak tersedia untuk diklaim.', 'status' => 409];
                }

                if (!in_array((string) ($barang->status_laporan ?? ''), [WorkflowStatus::REPORT_APPROVED, WorkflowStatus::REPORT_MATCHED, WorkflowStatus::REPORT_CLAIMED], true)) {
                    return ['ok' => false, 'message' => 'Barang temuan harus disetujui ' . $managerRoleLabelLower . ' terlebih dahulu sebelum klaim.', 'status' => 422];
                }

                if (!$barang->admin_id) {
                    return ['ok' => false, 'message' => 'Barang belum memiliki pengelola untuk memproses klaim.', 'status' => 409];
                }

                $assignedAdmin = Admin::query()
                    ->whereKey((int) $barang->admin_id)
                    ->where('status_verifikasi', Admin::STATUS_ACTIVE)
                    ->first();
                if (!$assignedAdmin || (!is_null($barang->region_id) && (int) $assignedAdmin->region_id !== (int) $barang->region_id)) {
                    return ['ok' => false, 'message' => 'Barang belum memiliki pengelola aktif untuk memproses klaim.', 'status' => 409];
                }

                if ($laporan) {
                    if (!in_array((string) $laporan->status_laporan, [WorkflowStatus::REPORT_APPROVED, WorkflowStatus::REPORT_MATCHED, WorkflowStatus::REPORT_CLAIMED], true)) {
                        return ['ok' => false, 'message' => 'Laporan barang hilang harus disetujui ' . $managerRoleLabelLower . ' terlebih dahulu sebelum klaim.', 'status' => 422];
                    }

                    $pencocokan = Pencocokan::query()
                        ->where('laporan_hilang_id', (int) $laporan->id)
                        ->where('barang_id', (int) $barang->id)
                        ->latest('updated_at')
                        ->lockForUpdate()
                        ->first();
                }

                $hasDuplicateClaim = Klaim::query()
                    ->where('user_id', (int) $user->id)
                    ->where('barang_id', (int) $barang->id)
                    ->activeForSubmission()
                    ->exists();
                if ($hasDuplicateClaim) {
                    return ['ok' => false, 'message' => 'Anda sudah pernah mengajukan klaim aktif untuk barang ini.', 'status' => 409];
                }

                $hasBlockingClaimForBarang = Klaim::query()
                    ->where('barang_id', (int) $barang->id)
                    ->activeForSubmission()
                    ->exists();
                if ($hasBlockingClaimForBarang) {
                    return ['ok' => false, 'message' => 'Barang ini sedang tidak tersedia untuk diklaim.', 'status' => 409];
                }

                if ($laporan) {
                    $hasBlockingClaimForReport = Klaim::query()
                        ->where('laporan_hilang_id', (int) $laporan->id)
                        ->activeForSubmission()
                        ->exists();
                    if ($hasBlockingClaimForReport) {
                        return ['ok' => false, 'message' => 'Laporan ini masih punya klaim aktif. Tunggu proses klaim sebelumnya selesai.', 'status' => 409];
                    }
                }

                if ($laporan && (!$pencocokan || !in_array((string) $pencocokan->status_pencocokan, [WorkflowStatus::MATCH_CONFIRMED, WorkflowStatus::MATCH_CLAIM_IN_PROGRESS, WorkflowStatus::MATCH_CLAIM_REJECTED], true))) {
                    return ['ok' => false, 'message' => 'Barang ini belum ditandai cocok oleh ' . $managerRoleLabelLower . ' dengan laporan Anda.', 'status' => 422];
                }

                if ($laporan) {
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
                }

                foreach ($photos as $photo) {
                    $buktiFotoPaths[] = $photo->store('private/verifikasi-klaim/' . now()->format('Y/m'), 'local');
                }

                $klaim = Klaim::create([
                    'laporan_hilang_id' => $laporan ? (int) $laporan->id : null,
                    'barang_id' => (int) $barang->id,
                    'pencocokan_id' => $pencocokan ? (int) $pencocokan->id : null,
                    'user_id' => (int) $user->id,
                    'admin_id' => (int) $assignedAdmin->id,
                    'status_klaim' => 'pending',
                    'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
                    'catatan' => $validated['catatan'] ?? null,
                    'kontak' => $validated['kontak_pelapor'],
                    'bukti_foto' => $buktiFotoPaths,
                    'bukti_kepemilikan' => $validated['bukti_kepemilikan'],
                    'bukti_ciri_khusus' => $validated['bukti_ciri_khusus'],
                    'bukti_detail_isi' => $validated['bukti_detail_isi'] ?? null,
                    'bukti_lokasi_spesifik' => $validated['bukti_lokasi_spesifik'],
                    'bukti_waktu_hilang' => $this->normalizeClaimTime((string) $validated['bukti_waktu_hilang']),
                ]);

                if ($barang->status_barang === 'tersedia') {
                    $barang->update(['status_barang' => 'dalam_proses_klaim']);
                }
                if ($laporan) {
                    $laporan->update(['status_laporan' => WorkflowStatus::REPORT_CLAIMED]);
                }
                if ($pencocokan) {
                    $pencocokan->update(['status_pencocokan' => WorkflowStatus::MATCH_CLAIM_IN_PROGRESS]);
                }

                return [
                    'ok' => true,
                    'message' => 'Pengajuan klaim berhasil dikirim. Pantau status verifikasi di Riwayat Klaim.',
                    'claim' => $klaim,
                ];
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($buktiFotoPaths);
            throw $exception;
        }
    }

    private function positiveIntegerOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
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
