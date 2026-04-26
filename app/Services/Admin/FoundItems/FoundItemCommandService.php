<?php

namespace App\Services\Admin\FoundItems;

use App\Models\Barang;
use App\Services\ReportImageCleaner;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Http\UploadedFile;

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
}
