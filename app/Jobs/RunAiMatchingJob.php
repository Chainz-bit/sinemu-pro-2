<?php

namespace App\Jobs;

use App\Models\Klaim;
use App\Models\Pencocokan;
use App\Services\GeminiMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * RunAiMatchingJob
 *
 * Job antrian untuk menjalankan pencocokan AI antara laporan hilang
 * dan barang temuan yang terhubung melalui sebuah Klaim.
 *
 * Dipanggil setelah klaim diajukan (SubmitClaimAction) atau saat
 * admin meminta re-analisis dari halaman detail verifikasi klaim.
 *
 * Job ini berjalan asinkron sehingga tidak memblokir respons HTTP.
 */
class RunAiMatchingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Jumlah percobaan ulang jika job gagal */
    public int $tries = 2;

    /** Batas waktu eksekusi job (detik) */
    public int $timeout = 90;

    public function __construct(
        private readonly int $klaimId
    ) {
    }

    /**
     * Jalankan job: ambil data klaim, laporan hilang, barang temuan,
     * lalu panggil GeminiMatchingService dan simpan hasilnya.
     */
    public function handle(GeminiMatchingService $matchingService): void
    {
        $klaim = Klaim::with([
            'laporanHilang:id,kategori_id,nama_barang,kategori_barang,warna_barang,merek_barang,nomor_seri,'
                . 'lokasi_hilang,tanggal_hilang,keterangan,ciri_khusus,foto_barang',
            'laporanHilang.kategori:id,nama_kategori',
            'barang:id,kategori_id,nama_barang,warna_barang,merek_barang,nomor_seri,'
                . 'lokasi_ditemukan,tanggal_ditemukan,deskripsi,ciri_khusus,foto_barang',
            'barang.kategori:id,nama_kategori',
            'pencocokan:id,laporan_hilang_id,barang_id',
        ])->find($this->klaimId);

        if ($klaim === null) {
            Log::warning("RunAiMatchingJob: Klaim #{$this->klaimId} tidak ditemukan.");
            return;
        }

        $laporan = $klaim->laporanHilang;
        $barang  = $klaim->barang;

        if ($laporan === null || $barang === null) {
            Log::warning("RunAiMatchingJob: Laporan hilang atau barang temuan tidak lengkap untuk Klaim #{$this->klaimId}.");
            return;
        }

        // Siapkan data untuk service
        $laporanData = array_merge(
            $laporan->toArray(),
            ['nama_kategori' => $laporan->kategori?->nama_kategori]
        );
        $barangData = array_merge(
            $barang->toArray(),
            ['nama_kategori' => $barang->kategori?->nama_kategori]
        );

        // Jalankan pencocokan AI
        $result = $matchingService->match($laporanData, $barangData);

        if (!$result['success']) {
            Log::error("RunAiMatchingJob: Gagal menjalankan AI matching untuk Klaim #{$this->klaimId}.", [
                'error' => $result['error'],
            ]);
            return;
        }

        // Simpan hasil ke tabel pencocokans (jika ada record pencocokan)
        $pencocokan = $klaim->pencocokan;

        if ($pencocokan instanceof Pencocokan) {
            $pencocokan->update([
                'ai_similarity_score' => $result['similarity_score'],
                'ai_recommendation'   => $result['recommendation'],
                'ai_reasoning'        => $result['reasoning'],
                'ai_matched_at'       => now(),
                'ai_model_used'       => $result['model_used'],
            ]);

            Log::info("RunAiMatchingJob: AI matching selesai untuk Klaim #{$this->klaimId}.", [
                'score'          => $result['similarity_score'],
                'recommendation' => $result['recommendation'],
                'model'          => $result['model_used'],
            ]);
        } else {
            // Belum ada pencocokan resmi — simpan langsung ke klaim sebagai cache sementara
            // (pencocokan belum dibuat oleh admin; AI score disimpan ke klaim itu sendiri)
            Log::info("RunAiMatchingJob: Tidak ada pencocokan terkait untuk Klaim #{$this->klaimId}. "
                . 'AI score tidak disimpan ke pencocokans.');
        }
    }

    /**
     * Tangani kegagalan job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("RunAiMatchingJob: Job gagal untuk Klaim #{$this->klaimId}.", [
            'error' => $exception->getMessage(),
        ]);
    }
}
