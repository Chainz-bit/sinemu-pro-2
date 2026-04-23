<?php

namespace App\Services\Admin\LostItems;

use App\Models\LaporanBarangHilang;
use App\Services\ReportImageCleaner;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class LostItemCommandService
{
    public function update(LaporanBarangHilang $item, array $validated, ?UploadedFile $photo, OptimizedImageUploader $uploader): void
    {
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $item->sumber_laporan !== 'lapor_hilang') {
            abort(404);
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
        if ($photo) {
            $oldPhotoPath = $item->foto_barang;
            $payload['foto_barang'] = $uploader->upload($photo, 'barang-hilang/' . now()->format('Y/m'));
        }

        $item->update($payload);

        if (!empty($oldPhotoPath)) {
            ReportImageCleaner::purgeIfOrphaned($oldPhotoPath);
        }
    }

    public function destroy(LaporanBarangHilang $item): void
    {
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            abort_if($item->sumber_laporan !== 'lapor_hilang', 404);
        }

        $photoPath = $item->foto_barang;
        $item->delete();
        ReportImageCleaner::purgeIfOrphaned($photoPath);
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

        $item->update([
            'status_laporan' => $newStatus,
            'verified_by_admin_id' => (int) Auth::guard('admin')->id(),
            'verified_at' => now(),
            'tampil_di_home' => $newStatus === WorkflowStatus::REPORT_APPROVED,
        ]);

        if (!is_null($item->user_id)) {
            $label = $newStatus === WorkflowStatus::REPORT_APPROVED ? 'disetujui' : 'ditolak';
            UserNotificationService::notifyUser(
                userId: (int) $item->user_id,
                type: 'verifikasi_laporan_hilang',
                title: 'Verifikasi Laporan Hilang',
                message: 'Laporan barang hilang "' . $item->nama_barang . '" ' . $label . ' admin.',
                actionUrl: route('user.dashboard'),
                meta: ['laporan_hilang_id' => $item->id]
            );
        }

        return ['ok' => true, 'message' => 'Verifikasi laporan barang hilang berhasil diperbarui.'];
    }
}
