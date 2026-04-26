<?php

namespace Tests\Unit;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Services\Admin\Matching\MatchingCandidateQueryService;
use App\Services\Admin\Matching\MatchingService;
use App\Services\Admin\Matching\MatchingScoreService;
use Tests\TestCase;

class MatchingServiceTest extends TestCase
{
    public function test_score_is_high_for_strongly_similar_items(): void
    {
        $scoreService = new MatchingScoreService();
        $service = new MatchingService(new MatchingCandidateQueryService($scoreService), $scoreService);

        $laporan = new LaporanBarangHilang([
            'nama_barang' => 'Laptop Asus ROG',
            'kategori_barang' => 'Elektronik',
            'warna_barang' => 'Hitam',
            'merek_barang' => 'Asus',
            'nomor_seri' => 'SN-123456',
            'lokasi_hilang' => 'Perpustakaan Kampus',
            'tanggal_hilang' => '2026-04-10',
            'keterangan' => 'Ada stiker merah di sudut',
            'ciri_khusus' => 'Stiker merah',
        ]);

        $barang = new Barang([
            'nama_barang' => 'Laptop ASUS ROG',
            'warna_barang' => 'Hitam',
            'merek_barang' => 'Asus',
            'nomor_seri' => 'SN-123456',
            'lokasi_ditemukan' => 'Perpustakaan',
            'tanggal_ditemukan' => '2026-04-11',
            'deskripsi' => 'Laptop gaming dengan stiker merah',
            'ciri_khusus' => 'Stiker merah',
        ]);
        $barang->setRelation('kategori', new Kategori(['nama_kategori' => 'Elektronik']));

        $result = $service->scoreForPair($laporan, $barang);

        $this->assertGreaterThanOrEqual(75, $result['score']);
        $this->assertNotEmpty($result['reasons']);
    }

    public function test_score_is_low_for_unrelated_items(): void
    {
        $scoreService = new MatchingScoreService();
        $service = new MatchingService(new MatchingCandidateQueryService($scoreService), $scoreService);

        $laporan = new LaporanBarangHilang([
            'nama_barang' => 'Dompet Kulit Coklat',
            'kategori_barang' => 'Aksesoris',
            'warna_barang' => 'Coklat',
            'merek_barang' => 'Levi',
            'nomor_seri' => '',
            'lokasi_hilang' => 'Kantin Timur',
            'tanggal_hilang' => '2026-04-01',
            'keterangan' => 'Berisi kartu mahasiswa',
            'ciri_khusus' => 'Ada gantungan kunci biru',
        ]);

        $barang = new Barang([
            'nama_barang' => 'Handphone Samsung',
            'warna_barang' => 'Hitam',
            'merek_barang' => 'Samsung',
            'nomor_seri' => 'IMEI-999',
            'lokasi_ditemukan' => 'Parkiran Barat',
            'tanggal_ditemukan' => '2026-04-15',
            'deskripsi' => 'Ponsel layar retak',
            'ciri_khusus' => 'Retak pojok kiri atas',
        ]);
        $barang->setRelation('kategori', new Kategori(['nama_kategori' => 'Elektronik']));

        $result = $service->scoreForPair($laporan, $barang);

        $this->assertLessThan(35, $result['score']);
    }
}
