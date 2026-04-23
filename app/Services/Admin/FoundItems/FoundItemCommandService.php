<?php

namespace App\Services\Admin\FoundItems;

use App\Models\Barang;
use App\Models\BarangStatusHistory;
use App\Services\ReportImageCleaner;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;
use App\Support\Media\OptimizedImageUploader;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class FoundItemCommandService
{
    public function update(Barang $barang, array $validated, ?UploadedFile $photo, OptimizedImageUploader $uploader): void
    {
        $payload = [
            'nama_barang' => $validated['nama_barang'],
            'kategori_id' => $validated['kategori_id'] ?? $barang->kategori_id,
            'warna_barang' => $validated['warna_barang'] ?? null,
            'merek_barang' => $validated['merek_barang'] ?? null,
            'nomor_seri' => $validated['nomor_seri'] ?? null,
            'deskripsi' => isset($validated['deskripsi']) && trim((string) $validated['deskripsi']) !== ''
                ? trim((string) $validated['deskripsi'])
                : ((string) ($barang->deskripsi ?? '')),
            'ciri_khusus' => isset($validated['ciri_khusus']) && trim((string) $validated['ciri_khusus']) !== ''
                ? trim((string) $validated['ciri_khusus'])
                : null,
            'nama_penemu' => isset($validated['nama_penemu']) && trim((string) $validated['nama_penemu']) !== ''
                ? trim((string) $validated['nama_penemu'])
                : null,
            'kontak_penemu' => isset($validated['kontak_penemu']) && trim((string) $validated['kontak_penemu']) !== ''
                ? trim((string) $validated['kontak_penemu'])
                : null,
            'lokasi_ditemukan' => $validated['lokasi_ditemukan'],
            'detail_lokasi_ditemukan' => isset($validated['detail_lokasi_ditemukan']) && trim((string) $validated['detail_lokasi_ditemukan']) !== ''
                ? trim((string) $validated['detail_lokasi_ditemukan'])
                : null,
            'tanggal_ditemukan' => $validated['tanggal_ditemukan'],
            'waktu_ditemukan' => $validated['waktu_ditemukan'] ?? null,
            'lokasi_pengambilan' => isset($validated['lokasi_pengambilan']) && trim((string) $validated['lokasi_pengambilan']) !== ''
                ? trim((string) $validated['lokasi_pengambilan'])
                : null,
            'alamat_pengambilan' => isset($validated['alamat_pengambilan']) && trim((string) $validated['alamat_pengambilan']) !== ''
                ? trim((string) $validated['alamat_pengambilan'])
                : null,
            'penanggung_jawab_pengambilan' => isset($validated['penanggung_jawab_pengambilan']) && trim((string) $validated['penanggung_jawab_pengambilan']) !== ''
                ? trim((string) $validated['penanggung_jawab_pengambilan'])
                : null,
            'kontak_pengambilan' => isset($validated['kontak_pengambilan']) && trim((string) $validated['kontak_pengambilan']) !== ''
                ? trim((string) $validated['kontak_pengambilan'])
                : null,
            'jam_layanan_pengambilan' => isset($validated['jam_layanan_pengambilan']) && trim((string) $validated['jam_layanan_pengambilan']) !== ''
                ? trim((string) $validated['jam_layanan_pengambilan'])
                : null,
            'catatan_pengambilan' => isset($validated['catatan_pengambilan']) && trim((string) $validated['catatan_pengambilan']) !== ''
                ? trim((string) $validated['catatan_pengambilan'])
                : null,
        ];

        $oldPhotoPath = null;
        if ($photo) {
            $oldPhotoPath = $barang->foto_barang;
            $payload['foto_barang'] = $uploader->upload($photo, 'barang-temuan/' . now()->format('Y/m'));
        }

        $barang->update($payload);

        if (!empty($oldPhotoPath)) {
            ReportImageCleaner::purgeIfOrphaned($oldPhotoPath);
        }
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function updateStatus(Barang $barang, array $validated): array
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        $oldStatus = (string) $barang->status_barang;
        $newStatus = (string) $validated['status_barang'];
        $latestClaim = $barang->klaims()->latest('updated_at')->first();
        $latestClaimVerificationStatus = Schema::hasColumn('klaims', 'status_verifikasi')
            ? (string) ($latestClaim?->status_verifikasi ?? '')
            : '';
        $latestClaimLegacyStatus = (string) ($latestClaim?->status_klaim ?? '');

        if ($newStatus === WorkflowStatus::FOUND_CLAIMED) {
            $canMarkClaimed = Schema::hasColumn('klaims', 'status_verifikasi')
                ? in_array($latestClaimVerificationStatus, [WorkflowStatus::CLAIM_APPROVED, WorkflowStatus::CLAIM_COMPLETED], true)
                : $latestClaimLegacyStatus === WorkflowStatus::CLAIM_LEGACY_APPROVED;

            if (!$canMarkClaimed) {
                return ['ok' => false, 'message' => 'Status "Sudah Diklaim" hanya bisa dipilih setelah klaim disetujui.'];
            }
        }

        if ($newStatus === WorkflowStatus::FOUND_RETURNED) {
            $canMarkCompleted = Schema::hasColumn('klaims', 'status_verifikasi')
                ? $latestClaimVerificationStatus === WorkflowStatus::CLAIM_COMPLETED
                : ($latestClaimLegacyStatus === WorkflowStatus::CLAIM_LEGACY_APPROVED && $oldStatus === WorkflowStatus::FOUND_CLAIMED);

            if (!$canMarkCompleted) {
                return ['ok' => false, 'message' => 'Status "Selesai" hanya bisa dipilih setelah klaim ditandai selesai pada Verifikasi Klaim.'];
            }
        }

        if ($oldStatus === $newStatus) {
            return ['ok' => true, 'message' => 'Tidak ada perubahan status yang disimpan.'];
        }

        $barang->update(['status_barang' => $newStatus]);

        BarangStatusHistory::create([
            'barang_id' => $barang->id,
            'admin_id' => $admin?->id,
            'status_lama' => $oldStatus,
            'status_baru' => $newStatus,
            'catatan' => $validated['catatan_status'] ?? null,
        ]);

        $statusLabel = match ($newStatus) {
            WorkflowStatus::FOUND_AVAILABLE => 'Tersedia',
            WorkflowStatus::FOUND_CLAIM_IN_PROGRESS => 'Dalam Proses Klaim',
            WorkflowStatus::FOUND_CLAIMED => 'Sudah Diklaim',
            WorkflowStatus::FOUND_RETURNED => 'Sudah Dikembalikan',
            default => $newStatus,
        };

        $barang->klaims()
            ->select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->each(function ($userId) use ($barang, $statusLabel) {
                UserNotificationService::notifyUser(
                    userId: (int) $userId,
                    type: 'status_barang_temuan',
                    title: 'Status Barang Temuan Diperbarui',
                    message: 'Admin memperbarui status ' . $barang->nama_barang . ' menjadi ' . $statusLabel . '.',
                    actionUrl: route('user.dashboard'),
                    meta: ['barang_id' => $barang->id]
                );
            });

        return ['ok' => true, 'message' => 'Perubahan status berhasil disimpan.'];
    }

    public function verify(Barang $barang, array $validated): void
    {
        $newStatus = $validated['status_laporan'] === 'approved'
            ? WorkflowStatus::REPORT_APPROVED
            : WorkflowStatus::REPORT_REJECTED;

        $barang->update([
            'status_laporan' => $newStatus,
            'verified_by_admin_id' => (int) Auth::guard('admin')->id(),
            'verified_at' => now(),
            'tampil_di_home' => $newStatus === WorkflowStatus::REPORT_APPROVED,
        ]);

        if (!is_null($barang->user_id)) {
            $label = $newStatus === WorkflowStatus::REPORT_APPROVED ? 'disetujui' : 'ditolak';
            UserNotificationService::notifyUser(
                userId: (int) $barang->user_id,
                type: 'verifikasi_laporan_temuan',
                title: 'Verifikasi Laporan Temuan',
                message: 'Laporan barang temuan "' . $barang->nama_barang . '" ' . $label . ' admin.',
                actionUrl: route('user.dashboard'),
                meta: ['barang_id' => $barang->id]
            );
        }
    }

    public function export(Barang $barang): Response
    {
        $barang->loadMissing(['kategori:id,nama_kategori', 'admin:id,nama,email']);

        $statusLabel = match ($barang->status_barang) {
            WorkflowStatus::FOUND_AVAILABLE => 'Tersedia',
            WorkflowStatus::FOUND_CLAIM_IN_PROGRESS => 'Dalam Proses Klaim',
            WorkflowStatus::FOUND_CLAIMED => 'Sudah Diklaim',
            WorkflowStatus::FOUND_RETURNED => 'Sudah Dikembalikan',
            default => 'Tidak Diketahui',
        };

        $photoDataUri = null;
        if (!empty($barang->foto_barang) && Storage::disk('public')->exists($barang->foto_barang)) {
            $absolutePath = Storage::disk('public')->path($barang->foto_barang);
            $mimeType = mime_content_type($absolutePath) ?: 'image/jpeg';
            $photoDataUri = 'data:' . $mimeType . ';base64,' . base64_encode((string) file_get_contents($absolutePath));
        }

        $pdf = Pdf::loadView('admin.pdf.found-item-report', [
            'barang' => $barang,
            'statusLabel' => $statusLabel,
            'photoDataUri' => $photoDataUri,
        ])->setPaper('a4');

        return $pdf->download('laporan-barang-temuan-' . $barang->id . '.pdf');
    }

    public function destroy(Barang $barang): void
    {
        $photoPath = $barang->foto_barang;
        $barang->delete();
        ReportImageCleaner::purgeIfOrphaned($photoPath);
    }
}
