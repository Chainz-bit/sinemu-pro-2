<?php

namespace App\Services;

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

    /**
     * Model yang akan dicoba secara berurutan jika model sebelumnya gagal
     */
    private const FALLBACK_MODELS = [
        'gemini-3.6-flash',
        'gemini-3.7-flash',
        'gemini-3.8-flash',
        'gemini-flash-latest',
    ];

    /**
     * Batas waktu request ke Gemini API (detik)
     */
    private const REQUEST_TIMEOUT = 20;

    /**
     * Prompt sistem untuk pencocokan barang
     */
    private const MATCHING_PROMPT_TEMPLATE = <<<'PROMPT'
        Kamu adalah asisten verifikasi barang yang ramah, jelas, dan profesional untuk platform Sinemu Indonesia.

        TUGAS:
        Bantu petugas loket menganalisis kesesuaian antara "Laporan Barang Hilang" dan "Barang Temuan" berikut dengan bahasa yang mudah dipahami orang awam.

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

        PANDUAN GAYA BAHASA (PERSONA):
        - Bertindaklah sebagai asisten verifikasi barang yang ramah, jelas, dan profesional.
        - HINDARI kata-kata teknis atau kaku seperti: "ciri pembeda mikro", "identik secara visual", "probabilistik", "diskrepansi", atau kalimat majemuk yang berbelit-belit.
        - Gunakan bahasa Indonesia yang lugas, terstruktur, dan langsung ke inti persoalan.

        STRUKTUR OUTPUT KETERANGAN AI (REASONING):
        Susun penjelasan pada kolom "reasoning" tepat menjadi 3 poin sederhana berikut:
        1. Ringkasan: 1 kalimat kesimpulan apakah barang cenderung cocok, meragukan, atau berbeda.
        2. Hal yang Cocok & Berbeda:
           - Jelaskan apa yang sama (misal warna, jenis barang, merek, atau lokasi).
           - Jelaskan apa yang janggal/berbeda (misal ada stiker yang hilang, warna beda, atau kondisi fisik tidak sesuai).
        3. Saran untuk Petugas: Berikan 1 langkah konkret apa yang perlu dicek langsung oleh petugas di loket (misalnya: "Minta pelapor menyebutkan isi dompet atau menunjukkan tanda pengenal").

        KEMBALIKAN RESPONS HANYA DALAM FORMAT JSON BERIKUT (tanpa teks pengantar, tanpa markdown, tanpa komentar):
        {
          "similarity_score": <angka bulat 0-100>,
          "recommendation": "<high|medium|low|uncertain>",
          "reasoning": "1. Ringkasan: ...\n2. Hal yang Cocok & Berbeda:\n- Cocok: ...\n- Berbeda/Janggal: ...\n3. Saran untuk Petugas: ..."
        }

        PANDUAN SKOR DAN REKOMENDASI:
        - 80-100 (high): Barang sangat cocok, sebagian besar detail dan fisik sesuai.
        - 60-79 (medium): Ada kemiripan kuat, namun masih ada detail yang perlu dikonfirmasi.
        - 40-59 (low): Kemiripan rendah atau meragukan, terdapat perbedaan mencolok.
        - 0-39 (uncertain): Barang jelas berbeda atau informasi sangat bertolak belakang.
        PROMPT;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    }

    /**
     * Jalankan pencocokan AI antara laporan hilang dan barang temuan.
     *
     * @param  array<string, mixed>  $laporanData  Data laporan barang hilang
     * @param  array<string, mixed>  $barangData  Data barang temuan
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
        $hasImages = ! empty($laporan['foto_barang']) || ! empty($barang['foto_barang']);
        $imageContext = $hasImages
            ? 'Foto kedua barang disertakan. Gunakan visual untuk memperkuat analisis.'
            : 'Tidak ada foto yang tersedia. Analisis berdasarkan data teks saja.';

        $replacements = [
            '{nama_hilang}' => $this->str($laporan['nama_barang'] ?? null),
            '{kategori_hilang}' => $this->str($laporan['nama_kategori'] ?? $laporan['kategori_barang'] ?? null),
            '{warna_hilang}' => $this->str($laporan['warna_barang'] ?? null),
            '{merek_hilang}' => $this->str($laporan['merek_barang'] ?? null),
            '{nomor_seri_hilang}' => $this->str($laporan['nomor_seri'] ?? null),
            '{lokasi_hilang}' => $this->str($laporan['lokasi_hilang'] ?? null),
            '{tanggal_hilang}' => $this->str($laporan['tanggal_hilang'] ?? null),
            '{keterangan_hilang}' => $this->str($laporan['keterangan'] ?? null),
            '{ciri_khusus_hilang}' => $this->str($laporan['ciri_khusus'] ?? null),
            '{nama_temuan}' => $this->str($barang['nama_barang'] ?? null),
            '{kategori_temuan}' => $this->str($barang['nama_kategori'] ?? $barang['kategori_id'] ?? $barang['kategori_barang'] ?? null),
            '{warna_temuan}' => $this->str($barang['warna_barang'] ?? null),
            '{merek_temuan}' => $this->str($barang['merek_barang'] ?? null),
            '{nomor_seri_temuan}' => $this->str($barang['nomor_seri'] ?? null),
            '{lokasi_temuan}' => $this->str($barang['lokasi_ditemukan'] ?? null),
            '{tanggal_temuan}' => $this->str($barang['tanggal_ditemukan'] ?? null),
            '{deskripsi_temuan}' => $this->str($barang['deskripsi'] ?? null),
            '{ciri_khusus_temuan}' => $this->str($barang['ciri_khusus'] ?? null),
            '{image_context}' => $imageContext,
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
            'foto_hilang' => $laporan['foto_barang'] ?? null,
            'foto_temuan' => $barang['foto_barang'] ?? null,
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
            if (! Storage::disk('public')->exists($normalised)) {
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
                    'data' => base64_encode($fileContent),
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning("GeminiMatchingService: gagal membaca gambar [{$label}]", [
                'path' => $normalised,
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
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
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
                    'role' => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.0,
                'topP' => 0.1,
                'maxOutputTokens' => 2048,
                'responseMimeType' => 'application/json',  // minta JSON murni
                'thinkingConfig' => [
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

                    return null;  // coba model berikutnya
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
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 500),
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

        // Ekstraksi blok JSON menggunakan regex agar tahan terhadap teks pengantar atau markdown blocks
        if (preg_match('/\{[\s\S]*\}/', (string) $rawText, $matches)) {
            $cleanJson = $matches[0];
        } else {
            $cleanJson = trim((string) $rawText);
            $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson);
            $cleanJson = preg_replace('/\s*```$/', '', (string) $cleanJson);
            $cleanJson = trim((string) $cleanJson);
        }

        $decoded = json_decode($cleanJson, true);

        if (! is_array($decoded)) {
            Log::warning('GeminiMatchingService: gagal decode JSON', [
                'model' => $model,
                'raw' => substr((string) $rawText, 0, 300),
            ]);

            return null;
        }

        $score = isset($decoded['similarity_score']) ? (int) $decoded['similarity_score'] : null;
        $recommendation = isset($decoded['recommendation']) ? strtolower(trim((string) $decoded['recommendation'])) : null;

        $rawReasoning = $decoded['reasoning'] ?? null;
        if (is_array($rawReasoning)) {
            $parts = [];
            if (! empty($rawReasoning['ringkasan'])) {
                $parts[] = '1. Ringkasan: '.trim((string) $rawReasoning['ringkasan']);
            }
            if (! empty($rawReasoning['hal_cocok_dan_berbeda'])) {
                $cocokBerbeda = is_array($rawReasoning['hal_cocok_dan_berbeda'])
                    ? implode("\n", array_map(fn ($k, $v) => is_numeric($k) ? "- {$v}" : "- {$k}: {$v}", array_keys($rawReasoning['hal_cocok_dan_berbeda']), $rawReasoning['hal_cocok_dan_berbeda']))
                    : trim((string) $rawReasoning['hal_cocok_dan_berbeda']);
                $parts[] = "2. Hal yang Cocok & Berbeda:\n".$cocokBerbeda;
            }
            if (! empty($rawReasoning['saran_petugas']) || ! empty($rawReasoning['saran_untuk_petugas'])) {
                $saran = $rawReasoning['saran_petugas'] ?? $rawReasoning['saran_untuk_petugas'];
                $parts[] = '3. Saran untuk Petugas: '.trim((string) $saran);
            }
            $reasoning = ! empty($parts) ? implode("\n", $parts) : json_encode($rawReasoning, JSON_UNESCAPED_UNICODE);
        } else {
            $reasoning = is_string($rawReasoning) ? trim($rawReasoning) : null;
        }

        // Fallback jika model mengembalikan field terpisah di root object
        if (empty($reasoning) && (! empty($decoded['ringkasan']) || ! empty($decoded['saran_petugas']))) {
            $parts = [];
            if (! empty($decoded['ringkasan'])) {
                $parts[] = '1. Ringkasan: '.trim((string) $decoded['ringkasan']);
            }
            if (! empty($decoded['hal_cocok_dan_berbeda'])) {
                $parts[] = '2. Hal yang Cocok & Berbeda: '.(is_array($decoded['hal_cocok_dan_berbeda']) ? implode(', ', $decoded['hal_cocok_dan_berbeda']) : trim((string) $decoded['hal_cocok_dan_berbeda']));
            }
            if (! empty($decoded['saran_petugas']) || ! empty($decoded['saran_untuk_petugas'])) {
                $saran = $decoded['saran_petugas'] ?? $decoded['saran_untuk_petugas'];
                $parts[] = '3. Saran untuk Petugas: '.trim((string) $saran);
            }
            $reasoning = implode("\n", $parts);
        }

        // Validasi nilai
        if ($score === null || $score < 0 || $score > 100) {
            Log::warning('GeminiMatchingService: skor tidak valid', ['score' => $score, 'model' => $model]);

            return null;
        }

        $allowedRecommendations = ['high', 'medium', 'low', 'uncertain'];
        if (! in_array($recommendation, $allowedRecommendations, true)) {
            // Tetapkan berdasarkan skor jika rekomendasi tidak valid
            $recommendation = match (true) {
                $score >= 80 => 'high',
                $score >= 60 => 'medium',
                $score >= 40 => 'low',
                default => 'uncertain',
            };
        }

        return [
            'success' => true,
            'similarity_score' => $score,
            'recommendation' => $recommendation,
            'reasoning' => $reasoning,
            'model_used' => $model,
            'error' => null,
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
        $models = array_unique(array_filter([
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
            'success' => false,
            'similarity_score' => null,
            'recommendation' => null,
            'reasoning' => null,
            'model_used' => null,
            'error' => $message,
        ];
    }
}
