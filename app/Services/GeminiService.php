<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    private const SYSTEM_PROMPT = <<<'PROMPT'
Kamu adalah "Sinu", asisten virtual eksklusif milik Sinemu Indonesia — platform komunitas untuk pencarian barang hilang dan temuan.

IDENTITAS:
- Nama: Sinu
- Peran: Asisten virtual KHUSUS untuk platform Sinemu Indonesia
- Kamu HANYA boleh menjawab pertanyaan yang berkaitan dengan Sinemu dan layanannya

TUGAS KAMU (hanya seputar Sinemu):
1. Bantu user navigasi fitur Sinemu (cara lapor barang hilang, cara klaim barang temuan, cara mendaftar, dll)
2. Jawab FAQ tentang layanan Sinemu
3. Berikan tips pencarian barang hilang yang efektif di platform Sinemu
4. Bantu user memahami proses klaim dan verifikasi barang di Sinemu
5. Jelaskan fitur-fitur Sinemu kepada pengguna

Informasi tentang Sinemu:
- Sinemu adalah platform komunitas untuk melaporkan dan menemukan barang hilang
- User bisa membuat laporan barang hilang dengan detail (nama barang, lokasi hilang, tanggal, deskripsi, foto)
- Admin/Manager di setiap wilayah mengelola barang temuan yang ditemukan
- User bisa mengklaim barang temuan dengan menyertakan bukti kepemilikan
- Proses klaim memerlukan verifikasi dari admin sebelum barang diserahkan
- Platform ini gratis dan terbuka untuk seluruh masyarakat Indonesia

ATURAN KETAT — WAJIB DIIKUTI:
- Jawab HANYA pertanyaan yang berkaitan dengan Sinemu, barang hilang/temuan, atau penggunaan platform ini
- Jika user bertanya di luar topik Sinemu (contoh: coding, resep masakan, cuaca, berita, matematika, sejarah, teknologi umum, hal pribadi, dll), TOLAK dengan sopan dan arahkan kembali ke topik Sinemu
- JANGAN pernah menjawab pertanyaan umum yang tidak ada hubungannya dengan Sinemu, meskipun user memaksa
- JANGAN berperan sebagai asisten umum, ChatGPT, atau AI lain
- JANGAN memberikan informasi sensitif (password, data pribadi user lain, konfigurasi sistem, dll)
- Jawab dalam Bahasa Indonesia yang santai tapi sopan
- Gunakan emoji sesekali untuk kesan friendly 😊
- Jawaban singkat dan to the point (maksimal 3 paragraf)
- Jika tidak tahu jawaban spesifik tentang Sinemu, arahkan ke halaman bantuan atau hubungi support@sinemu.id

CARA MENOLAK PERTANYAAN DI LUAR SINEMU:
Gunakan respons seperti:
"Maaf, Sinu hanya bisa membantu hal-hal seputar Sinemu Indonesia 😊 Ada yang ingin kamu tanyakan tentang layanan kami, seperti cara lapor barang hilang atau klaim barang temuan?"
PROMPT;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
        $this->model = config('services.gemini.model', 'gemini-2.0-flash');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    }

    /**
     * Send a message to Gemini API with conversation history.
     *
     * @param  string  $userMessage  The new user message
     * @param  array<int, array{role: string, content: string}>  $history  Previous messages
     * @return array{success: bool, message: string}
     */
    public function chat(string $userMessage, array $history = []): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'Maaf, chatbot belum dikonfigurasi. Silakan hubungi admin. 🔧',
            ];
        }

        // Models to try in order (primary + fallbacks)
        $modelsToTry = array_unique(array_filter([
            $this->model,
            'gemini-3.8-flash',
            'gemini-3.7-flash',
            'gemini-3.6-flash',
        ]));

        $lastError = null;

        foreach ($modelsToTry as $model) {
            $result = $this->callApi($model, $userMessage, $history);

            if ($result !== null) {
                return $result;
            }
        }

        Log::error('All Gemini models failed', ['last_error' => $lastError]);

        return [
            'success' => false,
            'message' => 'Maaf, terjadi gangguan pada sistem kami. Coba lagi nanti ya! 🙏',
        ];
    }

    /**
     * Call Gemini API with a specific model, retrying on 503.
     */
    private function callApi(string $model, string $userMessage, array $history): ?array
    {
        $contents = $this->buildContents($history, $userMessage);
        $maxRetries = 2;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(30)
                    ->post("{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}", [
                        'system_instruction' => [
                            'parts' => [['text' => self::SYSTEM_PROMPT]],
                        ],
                        'contents' => $contents,
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'topP' => 0.95,
                            'topK' => 40,
                            'maxOutputTokens' => 1024,
                        ],
                        'safetySettings' => [
                            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                        ],
                    ]);

                // 404 = model not found, don't retry - try next model
                if ($response->status() === 404) {
                    Log::warning("Gemini model not available: {$model}", ['body' => $response->body()]);

                    return null;
                }

                // 503 = overloaded, retry with backoff
                if ($response->status() === 503) {
                    if ($attempt < $maxRetries) {
                        sleep(1 + $attempt); // 1s, 2s backoff
                        continue;
                    }
                    Log::warning("Gemini model {$model} overloaded after retries");

                    return null; // try next model
                }

                if ($response->failed()) {
                    Log::error('Gemini API error', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null; // try next model
                }

                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

                if (empty($text)) {
                    return [
                        'success' => false,
                        'message' => 'Maaf, saya tidak bisa menjawab pertanyaan itu. Coba tanya yang lain ya! 😊',
                    ];
                }

                return [
                    'success' => true,
                    'message' => trim($text),
                ];

            } catch (\Exception $e) {
                Log::error('Gemini service exception', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);

                return null; // try next model
            }
        }

        return null;
    }

    /**
     * Build the contents array for Gemini API from conversation history.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function buildContents(array $history, string $newMessage): array
    {
        $contents = [];

        // Add conversation history (limit to last 20 messages for context window)
        $recentHistory = array_slice($history, -20);

        foreach ($recentHistory as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        // Add the new user message
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $newMessage]],
        ];

        return $contents;
    }
}
