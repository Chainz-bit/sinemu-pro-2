<?php

namespace App\Services\User\LostReports;

use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Services\ReportImageCleaner;
use App\Support\WorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class LostReportCommandService
{
    public function resolveEditableReport(int $userId, int $editId): ?LaporanBarangHilang
    {
        if ($editId <= 0) {
            return null;
        }

        $editingReport = LaporanBarangHilang::query()
            ->where('id', $editId)
            ->where('user_id', $userId)
            ->first();

        if (!$editingReport) {
            return null;
        }

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $editingReport->sumber_laporan !== 'lapor_hilang') {
            return null;
        }

        $hasBlockingClaim = Klaim::query()
            ->where('laporan_hilang_id', (int) $editingReport->id)
            ->whereIn('status_klaim', ['pending', 'disetujui'])
            ->exists();

        return $hasBlockingClaim ? null : $editingReport;
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function store(Request $request, array $validated): array
    {
        $reportId = isset($validated['report_id']) ? (int) $validated['report_id'] : null;
        $editingReport = null;
        if (!is_null($reportId)) {
            $editingReport = LaporanBarangHilang::query()
                ->where('id', $reportId)
                ->where('user_id', (int) Auth::id())
                ->first();

            if (!$editingReport) {
                return ['ok' => false, 'message' => 'Laporan tidak ditemukan atau bukan milik Anda.'];
            }

            if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $editingReport->sumber_laporan !== 'lapor_hilang') {
                return ['ok' => false, 'message' => 'Laporan ini tidak bisa diubah dari form ini.'];
            }

            $hasBlockingClaim = Klaim::query()
                ->where('laporan_hilang_id', (int) $editingReport->id)
                ->whereIn('status_klaim', ['pending', 'disetujui'])
                ->exists();
            if ($hasBlockingClaim) {
                return ['ok' => false, 'message' => 'Laporan yang sedang diproses tidak bisa diubah.'];
            }
        }

        $payload = [
            'user_id' => (int) Auth::id(),
            'nama_barang' => $validated['nama_barang'],
            'kategori_barang' => $validated['kategori_barang'] ?? null,
            'warna_barang' => $validated['warna_barang'] ?? null,
            'merek_barang' => $validated['merek_barang'] ?? null,
            'nomor_seri' => $validated['nomor_seri'] ?? null,
            'lokasi_hilang' => $validated['lokasi_hilang'],
            'detail_lokasi_hilang' => $validated['detail_lokasi_hilang'] ?? null,
            'tanggal_hilang' => $validated['tanggal_hilang'],
            'waktu_hilang' => $validated['waktu_hilang'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
            'ciri_khusus' => $validated['ciri_khusus'] ?? null,
            'kontak_pelapor' => $validated['kontak_pelapor'] ?? null,
            'bukti_kepemilikan' => $validated['bukti_kepemilikan'] ?? null,
        ];

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $payload['sumber_laporan'] = 'lapor_hilang';
        }
        if (Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
            $payload['status_laporan'] = WorkflowStatus::REPORT_SUBMITTED;
            $payload['tampil_di_home'] = false;
        }

        $photo = $request->file('foto_barang');
        if ($photo) {
            $payload['foto_barang'] = $photo->store('barang-hilang/' . now()->format('Y/m'), 'public');
        }

        if ($editingReport) {
            $oldPhotoPath = $editingReport->foto_barang;
            if (!$photo) {
                unset($payload['foto_barang']);
            }

            $editingReport->update($payload);

            if ($photo && !empty($oldPhotoPath)) {
                ReportImageCleaner::purgeIfOrphaned($oldPhotoPath);
            }

            return ['ok' => true, 'message' => 'Laporan barang hilang berhasil diperbarui.'];
        }

        LaporanBarangHilang::create($payload);
        return ['ok' => true, 'message' => 'Laporan barang hilang berhasil dikirim.'];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function destroy(LaporanBarangHilang $item, int $userId): array
    {
        if ((int) $item->user_id !== $userId) {
            abort(403);
        }

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $item->sumber_laporan !== 'lapor_hilang') {
            return ['ok' => false, 'message' => 'Laporan ini tidak bisa dihapus.'];
        }

        $hasAnyClaim = Klaim::query()
            ->where('laporan_hilang_id', (int) $item->id)
            ->exists();
        if ($hasAnyClaim) {
            return ['ok' => false, 'message' => 'Laporan yang sudah diproses tidak bisa dihapus.'];
        }

        $photoPath = $item->foto_barang;
        $item->delete();
        ReportImageCleaner::purgeIfOrphaned($photoPath);

        return ['ok' => true, 'message' => 'Laporan berhasil dihapus.'];
    }
}
