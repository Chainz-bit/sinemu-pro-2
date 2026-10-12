<?php

namespace Tests\Unit\Services;

use App\Services\GeminiMatchingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiMatchingTest extends TestCase
{
    public function test_match_returns_error_when_api_key_is_empty(): void
    {
        Config::set('services.gemini.api_key', '');
        $service = new GeminiMatchingService;

        $result = $service->match([], []);

        $this->assertFalse($result['success']);
        $this->assertSame('Gemini API key tidak dikonfigurasi.', $result['error']);
        $this->assertNull($result['similarity_score']);
    }

    public function test_match_parses_valid_json_response_with_friendly_three_point_reasoning(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Config::set('services.gemini.model', 'gemini-2.0-flash');

        $sampleReasoning = "1. Ringkasan: Barang cenderung cocok karena jenis dan warna identik.\n2. Hal yang Cocok & Berbeda:\n- Cocok: Merek Asus dan warna hitam sama persis.\n- Berbeda/Janggal: Tidak ada stiker pada fisik barang temuan.\n3. Saran untuk Petugas: Minta pelapor menunjukkan nota pembelian atau nomor seri laptop.";

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'similarity_score' => 88,
                                        'recommendation' => 'high',
                                        'reasoning' => $sampleReasoning,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiMatchingService;
        $result = $service->match(
            ['nama_barang' => 'Laptop Asus', 'warna_barang' => 'Hitam'],
            ['nama_barang' => 'Laptop Asus ROG', 'warna_barang' => 'Hitam']
        );

        $this->assertTrue($result['success']);
        $this->assertSame(88, $result['similarity_score']);
        $this->assertSame('high', $result['recommendation']);
        $this->assertSame($sampleReasoning, $result['reasoning']);
        $this->assertStringContainsString('1. Ringkasan:', $result['reasoning']);
        $this->assertStringContainsString('2. Hal yang Cocok & Berbeda:', $result['reasoning']);
        $this->assertStringContainsString('3. Saran untuk Petugas:', $result['reasoning']);
        $this->assertSame('gemini-2.0-flash', $result['model_used']);
    }

    public function test_match_cleans_markdown_fenced_json_response(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Config::set('services.gemini.model', 'gemini-2.0-flash');

        $markdownWrapped = "```json\n".json_encode([
            'similarity_score' => 65,
            'recommendation' => 'medium',
            'reasoning' => "1. Ringkasan: Barang meragukan.\n2. Hal yang Cocok & Berbeda:\n- Cocok: Merek sama.\n3. Saran untuk Petugas: Cek nomor identitas.",
        ])."\n```";

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $markdownWrapped],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiMatchingService;
        $result = $service->match([], []);

        $this->assertTrue($result['success']);
        $this->assertSame(65, $result['similarity_score']);
        $this->assertSame('medium', $result['recommendation']);
    }

    public function test_match_infers_recommendation_when_invalid(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Config::set('services.gemini.model', 'gemini-2.0-flash');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'similarity_score' => 45,
                                        'recommendation' => 'invalid_label',
                                        'reasoning' => '1. Ringkasan: Kemiripan rendah.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiMatchingService;
        $result = $service->match([], []);

        $this->assertTrue($result['success']);
        $this->assertSame(45, $result['similarity_score']);
        // Score 40-59 should default to 'low'
        $this->assertSame('low', $result['recommendation']);
    }

    public function test_match_falls_back_when_first_model_fails(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Config::set('services.gemini.model', 'gemini-primary-model');

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-primary-model*' => Http::response(['error' => 'not found'], 404),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'similarity_score' => 95,
                                        'recommendation' => 'high',
                                        'reasoning' => '1. Ringkasan: Sangat cocok.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiMatchingService;
        $result = $service->match([], []);

        $this->assertTrue($result['success']);
        $this->assertSame(95, $result['similarity_score']);
        $this->assertSame('gemini-3.6-flash', $result['model_used']);
    }

    public function test_match_sends_deterministic_generation_config(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Config::set('services.gemini.model', 'gemini-2.0-flash');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'similarity_score' => 90,
                                        'recommendation' => 'high',
                                        'reasoning' => "1. Ringkasan: Sangat cocok.\n2. Hal yang Cocok & Berbeda:\n- Cocok: Kunci motor.\n3. Saran untuk Petugas: Cek STNK.",
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiMatchingService;
        $service->match(['nama_barang' => 'Kunci Motor'], ['nama_barang' => 'Kunci Motor']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['generationConfig'])
                && $data['generationConfig']['temperature'] === 0.0
                && $data['generationConfig']['topP'] === 0.1;
        });
    }

    public function test_match_handles_nested_reasoning_fields(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Config::set('services.gemini.model', 'gemini-2.0-flash');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'similarity_score' => 80,
                                        'recommendation' => 'high',
                                        'reasoning' => [
                                            'ringkasan' => 'Barang cenderung cocok.',
                                            'hal_cocok_dan_berbeda' => '- Cocok: Warna dompet cokelat.',
                                            'saran_petugas' => 'Minta pelapor menyebutkan isi kartu.',
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiMatchingService;
        $result = $service->match([], []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('1. Ringkasan: Barang cenderung cocok.', $result['reasoning']);
        $this->assertStringContainsString('2. Hal yang Cocok & Berbeda:', $result['reasoning']);
        $this->assertStringContainsString('3. Saran untuk Petugas: Minta pelapor menyebutkan isi kartu.', $result['reasoning']);
    }

    public function test_prompt_template_adheres_to_friendly_persona_and_three_points_structure(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Config::set('services.gemini.model', 'gemini-2.0-flash');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'similarity_score' => 85,
                                        'recommendation' => 'high',
                                        'reasoning' => "1. Ringkasan: Cocok.\n2. Hal yang Cocok & Berbeda:\n- Cocok: Laptop.\n3. Saran untuk Petugas: Cek KTP.",
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiMatchingService;
        $service->match(['nama_barang' => 'Laptop Asus'], ['nama_barang' => 'Laptop Asus']);

        Http::assertSent(function ($request) {
            $data = $request->data();
            $promptText = $data['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($promptText, 'asisten verifikasi barang yang ramah, jelas, dan profesional')
                && str_contains($promptText, '1. Ringkasan:')
                && str_contains($promptText, '2. Hal yang Cocok & Berbeda:')
                && str_contains($promptText, '3. Saran untuk Petugas:')
                && str_contains($promptText, 'ciri pembeda mikro')
                && str_contains($promptText, 'probabilistik');
        });
    }
}
