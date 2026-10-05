<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * GeminiMatchingService
 *
 * Melakukan pencocokan multimodal (teks + gambar) antara
 * Laporan Barang Hilang dan Barang Temuan menggunakan Google Gemini API.
 *
 * Hasilnya berupa skor kemiripan (0–100), label rekomendasi, dan penjelasan singkat.
 * Gambar dibaca langsung dari disk — TIDAK menggunakan URL localhost.
 */
class GeminiMatchingService
{
    private string $apiKey;
    private string $baseUrl;

    /** Model yang akan dicoba secara berurutan jika model sebelumnya gagal */
    private const FALLBACK_MODELS = [
        'gemini-3.6-flash',
        'gemini-3.7-flash',
        'gemini-3.8-flash',
        'gemini-flash-latest',
    ];

    /** Batas waktu request ke Gemini API (detik) */
    private const REQUEST_TIMEOUT = 20;

    /** Prompt sistem untuk pencocokan barang */
    private const MATCHING_PROMPT_TEMPLATE = <<<'PROMPT'
Kamu adalah sistem AI untuk pencocokan barang hilang dengan barang temuan di platform Sinemu Indonesia.

TUGAS:
Analisis kesamaan antara "Laporan Barang Hilang" dan "Barang Temuan" berikut, lalu berikan penilaian objektif.

DATA LAPORAN BARANG HILANG:
- Nama Barang    : {nama_hilang}
- Kategori       : {kategori_hilang}
- Warna          : {warna_hilang}
- Merek          : {merek_hilang}
- Nomor Seri     : {nomor_seri_hilang}
- Lokasi Hilang  : {lokasi_hilang}
- Tanggal Hilang : {tanggal_hilang}
- Deskripsi      : {keterangan_hilang}
- Ciri Khusus    : {ciri_khusus_hilang}

DATA BARANG TEMUAN:
- Nama Barang     : {nama_temuan}
- Kategori        : {kategori_temuan}
- Warna           : {warna_temuan}
- Merek           : {merek_temuan}
- Nomor Seri      : {nomor_seri_temuan}
- Lokasi Ditemukan: {lokasi_temuan}
- Tanggal Temuan  : {tanggal_temuan}
- Deskripsi       : {deskripsi_temuan}
- Ciri Khusus     : {ciri_khusus_temuan}

{image_context}

INSTRUKSI ANALISIS:
1. Bandingkan semua atribut teks secara holistik (nama, kategori, warna, merek, nomor seri, lokasi, tanggal, deskripsi, ciri khusus).
2. Jika ada foto, bandingkan juga secara visual (warna, bentuk, kondisi fisik, fitur khas).
3. Nomor seri atau ciri unik yang IDENTIK = bukti kuat kecocokan.
4. Nomor seri yang BERBEDA = indikasi kuat bahwa barang berbeda (score rendah).
5. Pertimbangkan jarak lokasi dan jarak waktu sebagai faktor pendukung.

KEMBALIKAN RESPONS HANYA DALAM FORMAT JSON BERIKUT (tanpa teks lain, tanpa markdown, tanpa komentar):
{
  "similarity_score": <integer 0-100>,
  "recommendation": "<high|medium|low|uncertain>",
  "reasoning": "<penjelasan singkat dalam 2-3 kalimat Bahasa Indonesia>"
}

ATURAN SKOR:
- 80-100: Sangat mirip / hampir pasti sama (high)
- 60-79 : Kemungkinan besar sama (medium)
- 40-59 : Mungkin sama, perlu verifikasi lebih lanjut (low)
- 0-39  : Kemungkinan besar berbeda (uncertain)
PROMPT;

    /** Prompt sistem untuk batch matching antara 1 laporan hilang dengan array kandidat temuan */
    private const BATCH_MATCHING_PROMPT_TEMPLATE = <<<'PROMPT'
Kamu adalah sistem AI untuk evaluasi pencocokan batch antara 1 (satu) "Laporan Barang Hilang" dengan daftar kandidat "Barang Temuan" di platform Sinemu Indonesia.

TUGAS:
Bandingkan Laporan Barang Hilang dengan SETIAP Kandidat Barang Temuan secara terpisah dan berikan penilaian objektif untuk masing-masing kandidat.

DATA LAPORAN BARANG HILANG:
- ID Laporan     : {laporan_id}
- Nama Barang    : {nama_hilang}
- Kategori       : {kategori_hilang}
- Warna          : {warna_hilang}
- Merek          : {merek_hilang}
- Nomor Seri     : {nomor_seri_hilang}
- Lokasi Hilang  : {lokasi_hilang}
- Tanggal Hilang : {tanggal_hilang}
- Deskripsi      : {keterangan_hilang}
- Ciri Khusus    : {ciri_khusus_hilang}

DAFTAR KANDIDAT BARANG TEMUAN:
{kandidat_list}

INSTRUKSI ANALISIS:
1. Evaluasi atribut teks (nama, kategori, warna, merek, nomor seri, lokasi, tanggal, deskripsi, ciri khusus) dan perbandingan visual foto jika ada.
2. Nomor seri atau ciri unik yang IDENTIK = bukti kuat kecocokan (score 80-100).
3. Nomor seri atau karakteristik fisik yang bertolak belakang = bukan barang yang sama (score 0-39).
4. Berikan label rekomendasi: "Sangat Cocok" (score >= 80), "Kemungkinan Cocok" (score 60-79), "Perlu Verifikasi" (score 40-59), atau "Kurang Cocok" (score 0-39).
5. Tuliskan ringkasan alasan perbandingan visual dan teks dalam 1-2 kalimat Bahasa Indonesia.
6. Berikan daftar fitur yang cocok dalam array `matched_features` (contoh: ["warna", "merek", "kategori"]).

KEMBALIKAN RESPONS HANYA BERUPA JSON ARRAY TERSTRUKTUR (tanpa markdown ```json, tanpa teks pengantar atau penutup):
[
  {
    "barang_id": 12,
    "score": 85,
    "recommendation": "Sangat Cocok",
    "reasoning": "Ringkasan perbandingan visual dan teks...",
    "matched_features": ["warna", "merek"]
  }
]
PROMPT;

    public function __construct()
    {
        $this->apiKey  = config('services.gemini.api_key', '');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    }

    /**
     * Jalankan pencocokan AI antara laporan hilang dan barang temuan.
     *
     * @param  array<string, mixed>  $laporanData   Data laporan barang hilang
     * @param  array<string, mixed>  $barangData    Data barang temuan
     * @return array{
     *   success: bool,
     *   similarity_score: int|null,
     *   recommendation: string|null,
     *   reasoning: string|null,
     *   model_used: string|null,
     *   error: string|null
     * }
     */
    public function match(array $laporanData, array $barangData): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResult('Gemini API key tidak dikonfigurasi.');
        }

        $prompt = $this->buildPrompt($laporanData, $barangData);
        $imageParts = $this->buildImageParts($laporanData, $barangData);

        foreach ($this->resolveModelsToTry() as $model) {
            $result = $this->callApi($model, $prompt, $imageParts);

            if ($result !== null) {
                return $result;
            }
        }

        return $this->errorResult('Semua model Gemini gagal merespons.');
    }

    /**
     * Evaluasi pencocokan batch antara 1 laporan hilang dan sekumpulan kandidat barang temuan
     * dalam satu kali panggilan multimodal API ke Gemini.
     *
     * @param  LaporanBarangHilang  $laporan
     * @param  Collection<int, Barang>  $kandidatBarang
     * @return array<int, array<string, mixed>>
     */
    public function batchMatchLostWithFoundCandidates(LaporanBarangHilang $laporan, Collection $kandidatBarang): array
    {
        // 1. Jika koleksi kandidat kosong, langsung kembalikan array kosong (hemat kuota)
        if ($kandidatBarang->isEmpty()) {
            return [];
        }

        // Batasi maksimal 4 kandidat
        if ($kandidatBarang->count() > 4) {
            $kandidatBarang = $kandidatBarang->take(4);
        }

        if (empty($this->apiKey)) {
            Log::warning('GeminiMatchingService: API key tidak dikonfigurasi untuk batch matching.');
            return [];
        }

        $prompt = $this->buildBatchPrompt($laporan, $kandidatBarang);
        $imageParts = $this->buildBatchImageParts($laporan, $kandidatBarang);

        $parsedResults = null;
        $usedModel = null;

        foreach ($this->resolveModelsToTry() as $model) {
            $parsedResults = $this->callBatchApi($model, $prompt, $imageParts);
            if ($parsedResults !== null) {
                $usedModel = $model;
                break;
            }
        }

        if ($parsedResults === null) {
            Log::error('GeminiMatchingService: Semua model Gemini gagal dalam batch matching.', [
                'laporan_id' => $laporan->id,
            ]);
            return [];
        }

        // Petakan hasil evaluasi per barang_id
        $resultsById = [];
        foreach ($parsedResults as $row) {
            if (isset($row['barang_id'])) {
                $resultsById[(int) $row['barang_id']] = $row;
            }
        }

        $savedMatches = [];

        foreach ($kandidatBarang as $kandidat) {
            $eval = $resultsById[$kandidat->id] ?? null;
            if (!$eval) {
                continue;
            }

            $score = isset($eval['score']) ? max(0, min(100, (int) $eval['score'])) : 0;
            $rawRec = (string) ($eval['recommendation'] ?? '');
            $reasoning = (string) ($eval['reasoning'] ?? '');
            $matchedFeatures = is_array($eval['matched_features'] ?? null) ? $eval['matched_features'] : [];

            // Normalisasi kode rekomendasi untuk kolom database: high, medium, low, uncertain
            $recCode = match (strtolower(trim($rawRec))) {
                'sangat cocok', 'high'        => 'high',
                'kemungkinan cocok', 'medium' => 'medium',
                'perlu verifikasi', 'low'     => 'low',
                'kurang cocok', 'uncertain'   => 'uncertain',
                default => match (true) {
                    $score >= 80 => 'high',
                    $score >= 60 => 'medium',
                    $score >= 40 => 'low',
                    default      => 'uncertain',
                },
            };

            // Simpan / update di tabel pencocokans
            $pencocokan = Pencocokan::firstOrNew([
                'laporan_hilang_id' => $laporan->id,
                'barang_id'         => $kandidat->id,
            ]);

            $pencocokan->ai_similarity_score = $score;
            $pencocokan->ai_recommendation   = $recCode;
            $pencocokan->ai_reasoning        = $reasoning;
            $pencocokan->ai_matched_at       = now();
            $pencocokan->ai_model_used       = $usedModel;

            if (empty($pencocokan->status_pencocokan)) {
                $pencocokan->status_pencocokan = 'pending';
            }

            $pencocokan->save();

            $savedMatches[] = [
                'barang_id'           => $kandidat->id,
                'score'               => $score,
                'recommendation'      => $pencocokan->aiRecommendationLabel(),
                'recommendation_code' => $recCode,
                'reasoning'           => $reasoning,
                'matched_features'    => $matchedFeatures,
                'pencocokan_id'       => $pencocokan->id,
            ];
        }

        return $savedMatches;
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Bangun teks prompt dari template, substitusi data aktual.
     *
     * @param  array<string, mixed>  $laporan
     * @param  array<string, mixed>  $barang
     */
    private function buildPrompt(array $laporan, array $barang): string
    {
        $hasImages = !empty($laporan['foto_barang']) || !empty($barang['foto_barang']);
        $imageContext = $hasImages
            ? 'Foto kedua barang disertakan. Gunakan visual untuk memperkuat analisis.'
            : 'Tidak ada foto yang tersedia. Analisis berdasarkan data teks saja.';

        $replacements = [
            '{nama_hilang}'       => $this->str($laporan['nama_barang'] ?? null),
            '{kategori_hilang}'   => $this->str($laporan['nama_kategori'] ?? $laporan['kategori_barang'] ?? null),
            '{warna_hilang}'      => $this->str($laporan['warna_barang'] ?? null),
            '{merek_hilang}'      => $this->str($laporan['merek_barang'] ?? null),
            '{nomor_seri_hilang}' => $this->str($laporan['nomor_seri'] ?? null),
            '{lokasi_hilang}'     => $this->str($laporan['lokasi_hilang'] ?? null),
            '{tanggal_hilang}'    => $this->str($laporan['tanggal_hilang'] ?? null),
            '{keterangan_hilang}' => $this->str($laporan['keterangan'] ?? null),
            '{ciri_khusus_hilang}'=> $this->str($laporan['ciri_khusus'] ?? null),

            '{nama_temuan}'       => $this->str($barang['nama_barang'] ?? null),
            '{kategori_temuan}'   => $this->str($barang['nama_kategori'] ?? $barang['kategori_id'] ?? $barang['kategori_barang'] ?? null),
            '{warna_temuan}'      => $this->str($barang['warna_barang'] ?? null),
            '{merek_temuan}'      => $this->str($barang['merek_barang'] ?? null),
            '{nomor_seri_temuan}' => $this->str($barang['nomor_seri'] ?? null),
            '{lokasi_temuan}'     => $this->str($barang['lokasi_ditemukan'] ?? null),
            '{tanggal_temuan}'    => $this->str($barang['tanggal_ditemukan'] ?? null),
            '{deskripsi_temuan}'  => $this->str($barang['deskripsi'] ?? null),
            '{ciri_khusus_temuan}'=> $this->str($barang['ciri_khusus'] ?? null),

            '{image_context}'     => $imageContext,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), self::MATCHING_PROMPT_TEMPLATE);
    }

    /**
     * Siapkan parts gambar (inline_data) dari path penyimpanan lokal.
     * Membaca file fisik dari disk — TIDAK menggunakan URL.
     *
     * @param  array<string, mixed>  $laporan
     * @param  array<string, mixed>  $barang
     * @return array<int, array<string, mixed>>
     */
    private function buildImageParts(array $laporan, array $barang): array
    {
        $parts = [];

        $paths = array_filter([
            'foto_hilang'  => $laporan['foto_barang'] ?? null,
            'foto_temuan'  => $barang['foto_barang'] ?? null,
        ]);

        foreach ($paths as $label => $rawPath) {
            $part = $this->readImageAsInlineData((string) $rawPath, $label);
            if ($part !== null) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * Baca gambar dari storage disk dan kembalikan dalam format inline_data Gemini.
     *
     * @return array<string, mixed>|null
     */
    private function readImageAsInlineData(string $rawPath, string $label): ?array
    {
        if (trim($rawPath) === '') {
            return null;
        }

        // Normalkan path: hapus prefix 'storage/' atau 'public/'
        $normalised = ltrim(str_replace('\\', '/', $rawPath), '/');
        foreach (['storage/', 'public/'] as $prefix) {
            if (str_starts_with($normalised, $prefix)) {
                $normalised = substr($normalised, strlen($prefix));
                break;
            }
        }

        try {
            $disk = 'public';
            if (!Storage::disk('public')->exists($normalised)) {
                if (Storage::disk('local')->exists($normalised)) {
                    $disk = 'local';
                } else {
                    Log::warning("GeminiMatchingService: file tidak ditemukan [{$label}]", [
                        'path' => $normalised,
                    ]);

                    return null;
                }
            }

            $fileContent = Storage::disk($disk)->get($normalised);
            if ($fileContent === null || $fileContent === '') {
                return null;
            }

            $mimeType = $this->detectMimeType($normalised);

            return [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data'      => base64_encode($fileContent),
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning("GeminiMatchingService: gagal membaca gambar [{$label}]", [
                'path'  => $normalised,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Deteksi MIME type dari ekstensi file.
     */
    private function detectMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'image/jpeg',
        };
    }

    /**
     * Panggil Gemini API dengan model tertentu.
     * Return null jika gagal (agar caller bisa mencoba model berikutnya).
     *
     * @param  array<int, array<string, mixed>>  $imageParts
     * @return array<string, mixed>|null
     */
    private function callApi(string $model, string $prompt, array $imageParts): ?array
    {
        // Susun parts: teks prompt + gambar (jika ada)
        $parts = [['text' => $prompt]];
        foreach ($imageParts as $imgPart) {
            $parts[] = $imgPart;
        }

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.1,   // rendah untuk konsistensi analitik
                'topP'            => 0.9,
                'maxOutputTokens' => 2048,
                'responseMimeType'=> 'application/json', // minta JSON murni
                'thinkingConfig'  => [
                    'thinkingBudget' => 0,
                ],
            ],
        ];

        $maxRetries = 1;
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(self::REQUEST_TIMEOUT)
                    ->post(
                        "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}",
                        $payload
                    );

                if ($response->status() === 404) {
                    Log::info("GeminiMatchingService: model tidak ditemukan [{$model}]");
                    return null; // coba model berikutnya
                }

                if ($response->status() === 503) {
                    if ($attempt < $maxRetries) {
                        sleep(2);
                        continue;
                    }
                    Log::warning("GeminiMatchingService: model overloaded [{$model}]");
                    return null;
                }

                if ($response->failed()) {
                    Log::error('GeminiMatchingService: API error', [
                        'model'  => $model,
                        'status' => $response->status(),
                        'body'   => substr($response->body(), 0, 500),
                    ]);
                    return null;
                }

                return $this->parseApiResponse($response->json(), $model);

            } catch (\Throwable $e) {
                Log::error('GeminiMatchingService: exception', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        return null;
    }

    /**
     * Parse respons JSON dari Gemini dan kembalikan hasil terstruktur.
     *
     * @param  array<string, mixed>|null  $responseData
     * @return array<string, mixed>|null
     */
    private function parseApiResponse(?array $responseData, string $model): ?array
    {
        $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (empty($rawText)) {
            Log::warning('GeminiMatchingService: respons kosong dari API', ['model' => $model]);
            return null;
        }

        // Bersihkan: kadang Gemini masih membungkus dengan ```json ... ```
        $cleanJson = trim($rawText);
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson);
        $cleanJson = preg_replace('/\s*```$/', '', $cleanJson ?? '');
        $cleanJson = trim($cleanJson ?? '');

        $decoded = json_decode($cleanJson, true);

        if (!is_array($decoded)) {
            Log::warning('GeminiMatchingService: gagal decode JSON', [
                'model' => $model,
                'raw'   => substr($rawText, 0, 300),
            ]);
            return null;
        }

        $score          = isset($decoded['similarity_score']) ? (int) $decoded['similarity_score'] : null;
        $recommendation = isset($decoded['recommendation']) ? strtolower(trim((string) $decoded['recommendation'])) : null;
        $reasoning      = isset($decoded['reasoning']) ? trim((string) $decoded['reasoning']) : null;

        // Validasi nilai
        if ($score === null || $score < 0 || $score > 100) {
            Log::warning('GeminiMatchingService: skor tidak valid', ['score' => $score, 'model' => $model]);
            return null;
        }

        $allowedRecommendations = ['high', 'medium', 'low', 'uncertain'];
        if (!in_array($recommendation, $allowedRecommendations, true)) {
            // Tetapkan berdasarkan skor jika rekomendasi tidak valid
            $recommendation = match (true) {
                $score >= 80 => 'high',
                $score >= 60 => 'medium',
                $score >= 40 => 'low',
                default      => 'uncertain',
            };
        }

        return [
            'success'          => true,
            'similarity_score' => $score,
            'recommendation'   => $recommendation,
            'reasoning'        => $reasoning,
            'model_used'       => $model,
            'error'            => null,
        ];
    }

    /**
     * Resolusi daftar model: prioritaskan model dari config, lalu fallback.
     *
     * @return list<string>
     */
    private function resolveModelsToTry(): array
    {
        $configModel = config('services.gemini.model', '');
        $models      = array_unique(array_filter([
            $configModel,
            ...self::FALLBACK_MODELS,
        ]));

        return array_values($models);
    }

    /**
     * Konversi nilai ke string; kembalikan '-' jika null/kosong.
     */
    private function str(mixed $value): string
    {
        $trimmed = trim((string) ($value ?? ''));
        return $trimmed !== '' ? $trimmed : '-';
    }

    /**
     * Kembalikan struktur error yang konsisten.
     *
     * @return array<string, mixed>
     */
    private function errorResult(string $message): array
    {
        return [
            'success'          => false,
            'similarity_score' => null,
            'recommendation'   => null,
            'reasoning'        => null,
            'model_used'       => null,
            'error'            => $message,
        ];
    }
}
