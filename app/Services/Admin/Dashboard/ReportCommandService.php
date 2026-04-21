<?php

namespace App\Services\Admin\Dashboard;

use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Services\ReportImageCleaner;
use App\Support\WorkflowStatus;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ReportCommandService
{
    public function updateReport(
        Request $request,
        string $type,
        int $id,
        OptimizedImageUploader $imageUploader,
        int $adminId
    ): string {
        if ($type === 'hilang') {
            $report = LaporanBarangHilang::query()->findOrFail($id);
            if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $report->sumber_laporan !== 'lapor_hilang') {
                abort(404);
            }

            $validated = $request->validate([
                'nama_barang' => ['required', 'string', 'max:255'],
                'lokasi_hilang' => ['required', 'string', 'max:255'],
                'tanggal_hilang' => ['required', 'date'],
                'keterangan' => ['nullable', 'string', 'max:2000'],
                'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            ]);

            $payload = [
                'nama_barang' => $validated['nama_barang'],
                'lokasi_hilang' => $validated['lokasi_hilang'],
                'tanggal_hilang' => $validated['tanggal_hilang'],
                'keterangan' => isset($validated['keterangan']) && trim((string) $validated['keterangan']) !== ''
                    ? trim((string) $validated['keterangan'])
                    : null,
            ];

            $photo = $request->file('foto_barang');
            if ($photo) {
                $oldPhotoPath = $report->foto_barang;
                $payload['foto_barang'] = $imageUploader->upload($photo, 'barang-hilang/' . now()->format('Y/m'));
            }

            $report->update($payload);

            if (!empty($oldPhotoPath ?? null)) {
                ReportImageCleaner::purgeIfOrphaned($oldPhotoPath);
            }

            return 'Laporan barang hilang berhasil diperbarui.';
        }

        if ($type === 'temuan') {
            $report = Barang::query()->findOrFail($id);
            $validated = $request->validate([
                'nama_barang' => ['required', 'string', 'max:255'],
                'kategori_id' => ['nullable', 'integer', 'exists:kategoris,id'],
                'deskripsi' => ['nullable', 'string', 'max:2000'],
                'lokasi_ditemukan' => ['required', 'string', 'max:255'],
                'tanggal_ditemukan' => ['required', 'date'],
                'lokasi_pengambilan' => ['nullable', 'string', 'max:255'],
                'alamat_pengambilan' => ['nullable', 'string', 'max:255'],
                'penanggung_jawab_pengambilan' => ['nullable', 'string', 'max:255'],
                'kontak_pengambilan' => ['nullable', 'string', 'max:255'],
                'jam_layanan_pengambilan' => ['nullable', 'string', 'max:255'],
                'catatan_pengambilan' => ['nullable', 'string', 'max:2000'],
                'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            ]);

            $payload = [
                'nama_barang' => $validated['nama_barang'],
                'kategori_id' => $validated['kategori_id'] ?? $report->kategori_id,
                'deskripsi' => isset($validated['deskripsi']) && trim((string) $validated['deskripsi']) !== ''
                    ? trim((string) $validated['deskripsi'])
                    : ((string) ($report->deskripsi ?? '')),
                'lokasi_ditemukan' => $validated['lokasi_ditemukan'],
                'tanggal_ditemukan' => $validated['tanggal_ditemukan'],
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

            $photo = $request->file('foto_barang');
            if ($photo) {
                $oldPhotoPath = $report->foto_barang;
                $payload['foto_barang'] = $imageUploader->upload($photo, 'barang-temuan/' . now()->format('Y/m'));
            }

            $report->update($payload);

            if (!empty($oldPhotoPath ?? null)) {
                ReportImageCleaner::purgeIfOrphaned($oldPhotoPath);
            }

            return 'Laporan barang temuan berhasil diperbarui.';
        }

        if ($type === 'klaim') {
            $report = Klaim::query()->with('barang')->findOrFail($id);
            $validated = $request->validate([
                'status_klaim' => ['required', 'in:pending,disetujui,ditolak'],
                'catatan' => ['nullable', 'string', 'max:2000'],
            ]);

            $oldStatus = (string) $report->status_klaim;
            $newStatus = (string) $validated['status_klaim'];

            $report->update([
                'status_klaim' => $newStatus,
                'catatan' => $validated['catatan'] ?? null,
                'admin_id' => $adminId,
            ]);

            if ($report->barang && $oldStatus !== $newStatus) {
                if ($newStatus === 'disetujui') {
                    $report->barang->update(['status_barang' => 'sudah_diklaim']);
                } elseif ($newStatus === 'ditolak' && $report->barang->status_barang === 'dalam_proses_klaim') {
                    $report->barang->update(['status_barang' => 'tersedia']);
                }
            }

            return 'Data klaim berhasil diperbarui.';
        }

        abort(404);
    }

    /**
     * @return array{status:bool,message:string}
     */
    public function publishToHome(string $type, int $id): array
    {
        if ($type === 'hilang') {
            $report = LaporanBarangHilang::query()->findOrFail($id);
            if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $report->sumber_laporan !== 'lapor_hilang') {
                abort(404);
            }

            if (!Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
                return ['status' => false, 'message' => 'Kolom tampil_di_home belum tersedia pada laporan barang hilang.'];
            }
            if (Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')
                && (string) $report->status_laporan !== WorkflowStatus::REPORT_APPROVED) {
                return ['status' => false, 'message' => 'Laporan harus disetujui admin sebelum tampil di Home.'];
            }

            $report->update(['tampil_di_home' => true]);

            return ['status' => true, 'message' => 'Laporan barang hilang berhasil diupload ke Home.'];
        }

        if ($type === 'temuan') {
            $report = Barang::query()->findOrFail($id);
            if (!Schema::hasColumn('barangs', 'tampil_di_home')) {
                return ['status' => false, 'message' => 'Kolom tampil_di_home belum tersedia pada barang temuan.'];
            }
            if (Schema::hasColumn('barangs', 'status_laporan')
                && (string) $report->status_laporan !== WorkflowStatus::REPORT_APPROVED) {
                return ['status' => false, 'message' => 'Laporan harus disetujui admin sebelum tampil di Home.'];
            }

            $report->update(['tampil_di_home' => true]);

            return ['status' => true, 'message' => 'Laporan barang temuan berhasil diupload ke Home.'];
        }

        if ($type === 'klaim') {
            $claim = Klaim::query()->findOrFail($id);

            if (!is_null($claim->barang_id)) {
                $report = Barang::query()->find($claim->barang_id);
                if (!$report) {
                    return ['status' => false, 'message' => 'Data barang temuan terkait klaim tidak ditemukan.'];
                }

                if (!Schema::hasColumn('barangs', 'tampil_di_home')) {
                    return ['status' => false, 'message' => 'Kolom tampil_di_home belum tersedia pada barang temuan.'];
                }

                $report->update(['tampil_di_home' => true]);

                return ['status' => true, 'message' => 'Barang temuan dari klaim berhasil diupload ke Home.'];
            }

            if (!is_null($claim->laporan_hilang_id)) {
                $report = LaporanBarangHilang::query()->find($claim->laporan_hilang_id);
                if (!$report) {
                    return ['status' => false, 'message' => 'Data barang hilang terkait klaim tidak ditemukan.'];
                }

                if (!Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
                    return ['status' => false, 'message' => 'Kolom tampil_di_home belum tersedia pada laporan barang hilang.'];
                }

                $report->update(['tampil_di_home' => true]);

                return ['status' => true, 'message' => 'Barang hilang dari klaim berhasil diupload ke Home.'];
            }
        }

        abort(404);
    }
}
