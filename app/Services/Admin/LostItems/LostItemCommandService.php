<?php

namespace App\Services\Admin\LostItems;

use App\Models\LaporanBarangHilang;
use App\Services\ReportImageCleaner;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LostItemCommandService
{
    /**
     * @return array{ok:bool,message:string}
     */
    public function update(LaporanBarangHilang $item, array $validated, ?UploadedFile $photo, OptimizedImageUploader $uploader): array
    {
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $item->sumber_laporan !== 'lapor_hilang') {
            abort(404);
        }

        if (!$item->canBeEditedByAdmin()) {
            return ['ok' => false, 'message' => 'Laporan ini tidak dapat diedit karena sudah diproses.'];
        }

        $payload = [
            'nama_barang' => $validated['nama_barang'],
            'kategori_barang' => $validated['kategori_barang'] ?? null,
            'warna_barang' => $validated['warna_barang'] ?? null,
            'merek_barang' => $validated['merek_barang'] ?? null,
            'nomor_seri' => $validated['nomor_seri'] ?? null,
            'lokasi_hilang' => $validated['lokasi_hilang'],
            'detail_lokasi_hilang' => isset($validated['detail_lokasi_hilang']) && trim((string) $validated['detail_lokasi_hilang']) !== ''
                ? trim((string) $validated['detail_lokasi_hilang'])
                : null,
            'tanggal_hilang' => $validated['tanggal_hilang'],
            'waktu_hilang' => $validated['waktu_hilang'] ?? null,
            'keterangan' => isset($validated['keterangan']) && trim((string) $validated['keterangan']) !== ''
                ? trim((string) $validated['keterangan'])
                : null,
            'ciri_khusus' => isset($validated['ciri_khusus']) && trim((string) $validated['ciri_khusus']) !== ''
                ? trim((string) $validated['ciri_khusus'])
                : null,
            'kontak_pelapor' => isset($validated['kontak_pelapor']) && trim((string) $validated['kontak_pelapor']) !== ''
                ? trim((string) $validated['kontak_pelapor'])
                : null,
            'bukti_kepemilikan' => isset($validated['bukti_kepemilikan']) && trim((string) $validated['bukti_kepemilikan']) !== ''
                ? trim((string) $validated['bukti_kepemilikan'])
                : null,
        ];

        $oldPhotoPath = null;
        $newPhotoPath = null;
        if ($photo) {
            $oldPhotoPath = $item->foto_barang;
            $newPhotoPath = $uploader->upload($photo, 'barang-hilang/' . now()->format('Y/m'));
            $payload['foto_barang'] = $newPhotoPath;
        }

        try {
            $item->update($payload);
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if (!empty($oldPhotoPath)) {
            ReportImageCleaner::purgeIfOrphaned($oldPhotoPath);
        }

        return ['ok' => true, 'message' => 'Data barang hilang berhasil diperbarui.'];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function destroy(LaporanBarangHilang $item): array
    {
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            abort_if($item->sumber_laporan !== 'lapor_hilang', 404);
        }

        if (!$item->canBeDeletedByAdmin()) {
            return ['ok' => false, 'message' => 'Laporan yang masih aktif tidak dapat dihapus.'];
        }

        if ($this->hasClaimOrMatchingHistory($item)) {
            return ['ok' => false, 'message' => 'Laporan ini tidak dapat dihapus karena sudah terhubung dengan proses klaim atau pencocokan.'];
        }

        $photoPath = $item->foto_barang;
        DB::transaction(static function () use ($item): void {
            $item->delete();
        });

        $this->purgeReportPhotoAfterCommit($photoPath);

        return ['ok' => true, 'message' => 'Laporan barang hilang berhasil dihapus.'];
    }

    private function hasClaimOrMatchingHistory(LaporanBarangHilang $item): bool
    {
        return $item->klaims()->exists()
            || $item->pencocokans()->exists()
            || $item->pencocokans()
                ->whereHas('barang', function ($query): void {
                    $query->whereIn('status_barang', [
                        WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
                        WorkflowStatus::FOUND_CLAIMED,
                    ]);
                })
                ->exists();
    }

    private function purgeReportPhotoAfterCommit(?string $photoPath): void
    {
        try {
            ReportImageCleaner::purgeIfOrphaned($photoPath);
        } catch (Throwable $exception) {
            Log::error('Foto laporan barang hilang gagal dihapus setelah record laporan dihapus.', [
                'path' => $photoPath,
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function verify(LaporanBarangHilang $item, array $validated): array
    {
        if (!Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
            return ['ok' => false, 'message' => 'Status verifikasi laporan belum tersedia di database.'];
        }

        $newStatus = $validated['status_laporan'] === 'approved'
            ? WorkflowStatus::REPORT_APPROVED
            : WorkflowStatus::REPORT_REJECTED;
        $oldStatus = (string) ($item->status_laporan ?? '');

        if (!$item->canBeVerifiedByAdmin()) {
            return ['ok' => false, 'message' => 'Laporan ini tidak dapat diverifikasi ulang karena sudah diproses.'];
        }

        if ($oldStatus === $newStatus) {
            return ['ok' => true, 'message' => 'Status laporan tidak berubah.'];
        }

        $item->update([
            'status_laporan' => $newStatus,
            'verified_by_admin_id' => (int) \App\Support\ManagerPortal::id(),
            'verified_at' => now(),
            'tampil_di_home' => $newStatus === WorkflowStatus::REPORT_APPROVED,
        ]);

        if (!is_null($item->user_id)) {
            $label = $newStatus === WorkflowStatus::REPORT_APPROVED ? 'disetujui' : 'ditolak';
            UserNotificationService::notifyUser(
                userId: (int) $item->user_id,
                type: 'verifikasi_laporan_hilang',
                title: 'Verifikasi Laporan Hilang',
                message: 'Laporan barang hilang "' . $item->nama_barang . '" ' . $label . ' ' . \App\Support\RoleLabels::managerLower() . '.',
                actionUrl: route('user.dashboard'),
                meta: ['laporan_hilang_id' => $item->id]
            );
        }

        return ['ok' => true, 'message' => 'Verifikasi laporan barang hilang berhasil diperbarui.'];
    }
}
