<?php

namespace App\Services\Admin\Dashboard;

use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Services\ReportImageCleaner;
use App\Support\WorkflowStatus;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReportCommandService
{
    public function updateReport(
        string $type,
        int $id,
        array $validated,
        ?UploadedFile $photo,
        OptimizedImageUploader $imageUploader,
        int $adminId
    ): string {
        return match ($type) {
            'hilang' => $this->updateLostReport($id, $validated, $photo, $imageUploader),
            'temuan' => $this->updateFoundReport($id, $validated, $photo, $imageUploader),
            'klaim' => $this->updateClaimReport($id, $validated, $adminId),
            default => abort(404),
        };
    }

    /**
     * @return array{status:bool,message:string}
     */
    public function publishToHome(string $type, int $id): array
    {
        return match ($type) {
            'hilang' => $this->publishLostReportToHome($id),
            'temuan' => $this->publishFoundReportToHome($id),
            'klaim' => $this->publishClaimToHome($id),
            default => abort(404),
        };
    }

    private function updateLostReport(int $id, array $validated, ?UploadedFile $photo, OptimizedImageUploader $imageUploader): string
    {
        $report = LaporanBarangHilang::query()->findOrFail($id);
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $report->sumber_laporan !== 'lapor_hilang') {
            abort(404);
        }
        $this->ensureAdminCanAccessLostReport($report);

        $payload = [
            'nama_barang' => $validated['nama_barang'],
            'lokasi_hilang' => $validated['lokasi_hilang'],
            'tanggal_hilang' => $validated['tanggal_hilang'],
            'keterangan' => isset($validated['keterangan']) && trim((string) $validated['keterangan']) !== ''
                ? trim((string) $validated['keterangan'])
                : null,
        ];

        $oldPhotoPath = null;
        $newPhotoPath = null;
        if ($photo) {
            $oldPhotoPath = $report->foto_barang;
            $newPhotoPath = $imageUploader->upload($photo, 'barang-hilang/' . now()->format('Y/m'));
            $payload['foto_barang'] = $newPhotoPath;
        }

        try {
            $report->update($payload);
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if (!empty($oldPhotoPath)) {
            ReportImageCleaner::purgeIfOrphaned($oldPhotoPath);
        }

        return 'Laporan barang hilang berhasil diperbarui.';
    }

    private function updateFoundReport(int $id, array $validated, ?UploadedFile $photo, OptimizedImageUploader $imageUploader): string
    {
        $report = Barang::query()->findOrFail($id);
        $this->ensureAdminCanAccessFoundReport($report);

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

        $oldPhotoPath = null;
        $newPhotoPath = null;
        if ($photo) {
            $oldPhotoPath = $report->foto_barang;
            $newPhotoPath = $imageUploader->upload($photo, 'barang-temuan/' . now()->format('Y/m'));
            $payload['foto_barang'] = $newPhotoPath;
        }

        try {
            $report->update($payload);
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if (!empty($oldPhotoPath)) {
            ReportImageCleaner::purgeIfOrphaned($oldPhotoPath);
        }

        return 'Laporan barang temuan berhasil diperbarui.';
    }

    private function updateClaimReport(int $id, array $validated, int $adminId): string
    {
        $report = Klaim::query()->with('barang')->findOrFail($id);
        $this->ensureAdminCanAccessClaim($report);

        $oldStatus = (string) $report->status_klaim;
        $newStatus = (string) $validated['status_klaim'];

        $report->update([
            'status_klaim' => $newStatus,
            'catatan' => $validated['catatan'] ?? null,
            'admin_id' => $adminId,
        ]);

        if ($report->barang && $oldStatus !== $newStatus) {
            if ($newStatus === WorkflowStatus::CLAIM_LEGACY_APPROVED) {
                $report->barang->update(['status_barang' => WorkflowStatus::FOUND_CLAIMED]);
            } elseif ($newStatus === WorkflowStatus::CLAIM_LEGACY_REJECTED && $report->barang->status_barang === WorkflowStatus::FOUND_CLAIM_IN_PROGRESS) {
                $report->barang->update(['status_barang' => WorkflowStatus::FOUND_AVAILABLE]);
            }
        }

        return 'Data klaim berhasil diperbarui.';
    }

    /**
     * @return array{status:bool,message:string}
     */
    private function publishLostReportToHome(int $id): array
    {
        $report = LaporanBarangHilang::query()->findOrFail($id);
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $report->sumber_laporan !== 'lapor_hilang') {
            abort(404);
        }
        $this->ensureAdminCanAccessLostReport($report);

        if (!Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
            return ['status' => false, 'message' => 'Kolom tampil_di_home belum tersedia pada laporan barang hilang.'];
        }
        if (Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')
            && (string) $report->status_laporan !== WorkflowStatus::REPORT_APPROVED) {
            return ['status' => false, 'message' => 'Laporan harus disetujui ' . \App\Support\RoleLabels::managerLower() . ' sebelum tampil di Home.'];
        }

        $report->update(['tampil_di_home' => true]);

        return ['status' => true, 'message' => 'Laporan barang hilang berhasil diupload ke Home.'];
    }

    /**
     * @return array{status:bool,message:string}
     */
    private function publishFoundReportToHome(int $id): array
    {
        $report = Barang::query()->findOrFail($id);
        $this->ensureAdminCanAccessFoundReport($report);

        if (!Schema::hasColumn('barangs', 'tampil_di_home')) {
            return ['status' => false, 'message' => 'Kolom tampil_di_home belum tersedia pada barang temuan.'];
        }
        if (Schema::hasColumn('barangs', 'status_laporan')
            && (string) $report->status_laporan !== WorkflowStatus::REPORT_APPROVED) {
            return ['status' => false, 'message' => 'Laporan harus disetujui ' . \App\Support\RoleLabels::managerLower() . ' sebelum tampil di Home.'];
        }

        $report->update(['tampil_di_home' => true]);

        return ['status' => true, 'message' => 'Laporan barang temuan berhasil diupload ke Home.'];
    }

    /**
     * @return array{status:bool,message:string}
     */
    private function publishClaimToHome(int $id): array
    {
        $claim = Klaim::query()->findOrFail($id);
        $this->ensureAdminCanAccessClaim($claim);

        if (!is_null($claim->barang_id)) {
            $report = Barang::query()->find($claim->barang_id);
            if (!$report) {
                return ['status' => false, 'message' => 'Data barang temuan terkait klaim tidak ditemukan.'];
            }
            $this->ensureAdminCanAccessFoundReport($report);

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
            $this->ensureAdminCanAccessLostReport($report);

            if (!Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
                return ['status' => false, 'message' => 'Kolom tampil_di_home belum tersedia pada laporan barang hilang.'];
            }

            $report->update(['tampil_di_home' => true]);

            return ['status' => true, 'message' => 'Barang hilang dari klaim berhasil diupload ke Home.'];
        }

        abort(404);
    }

    private function ensureAdminCanAccessFoundReport(Barang $barang): void
    {
        $admin = \App\Support\ManagerPortal::user();
        if (!Schema::hasColumn('barangs', 'region_id')) {
            return;
        }

        abort_if(!$admin || empty($admin->region_id), 403, ucfirst(\App\Support\RoleLabels::managerLower()) . ' belum memiliki wilayah akses.');
        abort_if((int) $barang->region_id !== (int) $admin->region_id, 403, 'Anda tidak memiliki akses ke barang dari wilayah lain.');
    }

    private function ensureAdminCanAccessLostReport(LaporanBarangHilang $report): void
    {
        $admin = \App\Support\ManagerPortal::user();
        if (!Schema::hasColumn('laporan_barang_hilangs', 'region_id')) {
            return;
        }

        abort_if(!$admin || empty($admin->region_id), 403, ucfirst(\App\Support\RoleLabels::managerLower()) . ' belum memiliki wilayah akses.');
        abort_if(empty($report->region_id), 403, 'Laporan belum memiliki wilayah yang dapat diproses.');
        abort_if((int) $report->region_id !== (int) $admin->region_id, 403, 'Anda tidak memiliki akses ke laporan dari wilayah lain.');
    }

    private function ensureAdminCanAccessClaim(Klaim $claim): void
    {
        $admin = \App\Support\ManagerPortal::user();
        abort_if(!$admin, 403);

        if (!is_null($claim->admin_id)) {
            abort_if((int) $claim->admin_id !== (int) $admin->id, 403, 'Anda tidak memiliki akses ke klaim ini.');
            return;
        }

        $claim->loadMissing(['barang:id,region_id', 'laporanHilang:id,region_id']);

        if ($claim->relationLoaded('barang') && $claim->barang) {
            $this->ensureAdminCanAccessFoundReport($claim->barang);
            return;
        }

        if ($claim->laporanHilang) {
            $this->ensureAdminCanAccessLostReport($claim->laporanHilang);
            return;
        }

        abort(403, 'Anda tidak memiliki akses ke klaim ini.');
    }
}
