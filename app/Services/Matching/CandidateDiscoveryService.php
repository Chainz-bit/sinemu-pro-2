<?php

namespace App\Services\Matching;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * CandidateDiscoveryService (Tahap 1: SQL Pre-Filter)
 *
 * Menyaring kandidat barang temuan atau laporan hilang yang memenuhi kriteria
 * wilayah, kategori, status, dan rentang waktu (maksimal 60 hari) sebelum dikirim
 * ke tahap evaluasi AI multimodal.
 */
class CandidateDiscoveryService
{
    /**
     * Cari kandidat barang temuan yang berpotensi cocok untuk sebuah laporan barang hilang.
     *
     * @param  LaporanBarangHilang  $laporan
     * @param  int  $limit
     * @return Collection<int, Barang>
     */
    public function findCandidateFoundItems(LaporanBarangHilang $laporan, int $limit = 4): Collection
    {
        // Wilayah dan kategori wajib ada
        if (empty($laporan->region_id) || empty($laporan->kategori_id)) {
            return new Collection();
        }

        $query = Barang::query()
            // Filter 1: Wilayah yang sama
            ->where('region_id', $laporan->region_id)
            // Filter 2: Kategori yang identik
            ->where('kategori_id', $laporan->kategori_id)
            // Filter 3: Status barang tersedia dan laporan disetujui
            ->where('status_barang', WorkflowStatus::FOUND_AVAILABLE)
            ->where('status_laporan', WorkflowStatus::REPORT_APPROVED);

        // Filter 4: Logika temporal: tanggal_ditemukan >= tanggal_hilang, rentang maksimal 60 hari
        if (!empty($laporan->tanggal_hilang)) {
            $tanggalHilang = Carbon::parse($laporan->tanggal_hilang)->startOfDay();
            $maxTanggalTemuan = $tanggalHilang->copy()->addDays(60)->endOfDay();

            $query->whereDate('tanggal_ditemukan', '>=', $tanggalHilang->toDateString())
                  ->whereDate('tanggal_ditemukan', '<=', $maxTanggalTemuan->toDateString());
        }

        // Filter 5: Singkirkan barang temuan yang sudah pernah ditolak untuk laporan ini
        $query->whereDoesntHave('pencocokans', function ($q) use ($laporan) {
            $q->where('laporan_hilang_id', $laporan->id)
              ->whereIn('status_pencocokan', ['rejected', 'ditolak', WorkflowStatus::MATCH_CANCELLED]);
        });

        // Sorting Heuristik:
        // Prioritas jika merek atau warna cocok
        $merek = trim((string) ($laporan->merek_barang ?? ''));
        $warna = trim((string) ($laporan->warna_barang ?? ''));

        $query->orderByRaw(
            "((CASE WHEN LOWER(merek_barang) = ? AND merek_barang IS NOT NULL AND merek_barang != '' THEN 2 ELSE 0 END) + " .
            "(CASE WHEN LOWER(warna_barang) = ? AND warna_barang IS NOT NULL AND warna_barang != '' THEN 1 ELSE 0 END)) DESC",
            [strtolower($merek), strtolower($warna)]
        );

        // Sekunder: tanggal_ditemukan ASC (paling dekat setelah tanggal hilang)
        $query->orderBy('tanggal_ditemukan', 'asc');

        return $query->select([
            'id', 'nama_barang', 'kategori_id', 'region_id', 'warna_barang',
            'merek_barang', 'nomor_seri', 'lokasi_ditemukan', 'tanggal_ditemukan',
            'deskripsi', 'ciri_khusus', 'foto_barang', 'status_barang', 'status_laporan',
        ])
        ->with(['kategori:id,nama_kategori', 'region:id,nama_wilayah'])
        ->limit($limit)
        ->get();
    }

    /**
     * Cari kandidat laporan barang hilang yang berpotensi cocok untuk sebuah barang temuan baru.
     *
     * @param  Barang  $barangTemuan
     * @param  int  $limit
     * @return Collection<int, LaporanBarangHilang>
     */
    public function findCandidateLostReports(Barang $barangTemuan, int $limit = 4): Collection
    {
        if (empty($barangTemuan->region_id) || empty($barangTemuan->kategori_id)) {
            return new Collection();
        }

        $query = LaporanBarangHilang::query()
            // Filter 1: Wilayah yang sama
            ->where('region_id', $barangTemuan->region_id)
            // Filter 2: Kategori yang identik
            ->where('kategori_id', $barangTemuan->kategori_id)
            // Filter 3: Status laporan approved
            ->where('status_laporan', WorkflowStatus::REPORT_APPROVED);

        // Filter 4: Logika temporal: tanggal_hilang <= tanggal_ditemukan, rentang maksimal 60 hari
        if (!empty($barangTemuan->tanggal_ditemukan)) {
            $tanggalDitemukan = Carbon::parse($barangTemuan->tanggal_ditemukan)->startOfDay();
            $minTanggalHilang = $tanggalDitemukan->copy()->subDays(60)->startOfDay();

            $query->whereDate('tanggal_hilang', '<=', $tanggalDitemukan->toDateString())
                  ->whereDate('tanggal_hilang', '>=', $minTanggalHilang->toDateString());
        }

        // Filter 5: Singkirkan laporan hilang yang sudah pernah ditolak untuk barang ini
        $query->whereDoesntHave('pencocokans', function ($q) use ($barangTemuan) {
            $q->where('barang_id', $barangTemuan->id)
              ->whereIn('status_pencocokan', ['rejected', 'ditolak', WorkflowStatus::MATCH_CANCELLED]);
        });

        // Sorting Heuristik:
        $merek = trim((string) ($barangTemuan->merek_barang ?? ''));
        $warna = trim((string) ($barangTemuan->warna_barang ?? ''));

        $query->orderByRaw(
            "((CASE WHEN LOWER(merek_barang) = ? AND merek_barang IS NOT NULL AND merek_barang != '' THEN 2 ELSE 0 END) + " .
            "(CASE WHEN LOWER(warna_barang) = ? AND warna_barang IS NOT NULL AND warna_barang != '' THEN 1 ELSE 0 END)) DESC",
            [strtolower($merek), strtolower($warna)]
        );

        // Sekunder: tanggal_hilang DESC (paling dekat sebelum tanggal ditemukan)
        $query->orderBy('tanggal_hilang', 'desc');

        return $query->select([
            'id', 'user_id', 'kategori_id', 'region_id', 'nama_barang', 'kategori_barang',
            'warna_barang', 'merek_barang', 'nomor_seri', 'lokasi_hilang', 'tanggal_hilang',
            'keterangan', 'ciri_khusus', 'foto_barang', 'status_laporan',
        ])
        ->with(['kategori:id,nama_kategori', 'region:id,nama_wilayah'])
        ->limit($limit)
        ->get();
    }
}
