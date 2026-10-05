<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\GeminiMatchingService;
use App\Support\WorkflowStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * AiMatchingTestSeeder
 *
 * Seeder pengujian komprehensif untuk memvalidasi penalaran dan akurasi skor
 * dari GeminiMatchingService dalam 2 skenario uji (High Match vs Low Match).
 */
class AiMatchingTestSeeder extends Seeder
{
    /**
     * Jalankan database seeder.
     */
    public function run(?GeminiMatchingService $geminiService = null): void
    {
        $geminiService = $geminiService ?? app(GeminiMatchingService::class);

        $this->command?->info('Menyiapkan entitas pengujian AI Matching...');

        // 1. Penyiapan Relasi Entitas: Minimal 1 Admin dan 1 User
        $admin = Admin::query()->where('status_verifikasi', Admin::STATUS_ACTIVE)->first()
            ?? Admin::query()->first();

        if (!$admin) {
            $admin = Admin::create([
                'nama'              => 'Admin Pengelola QA',
                'email'             => 'admin.qa@sinemu.test',
                'username'          => 'admin_qa',
                'password'          => Hash::make('password123'),
                'status_verifikasi' => Admin::STATUS_ACTIVE,
                'instansi'          => 'Pusat Pengelolaan Barang Temuan',
                'alamat_lengkap'    => 'Gedung Rektorat Lt. 1',
            ]);
        }

        $user = User::query()->first();
        if (!$user) {
            $user = User::create([
                'nama'          => 'Budi Santoso (QA)',
                'name'          => 'Budi Santoso (QA)',
                'username'      => 'budi_qa',
                'email'         => 'budi.qa@sinemu.test',
                'password'      => Hash::make('password123'),
                'nomor_telepon' => '081234567890',
            ]);
        }

        // Konsistensi Region / Wilayah
        $wilayah = Wilayah::first();
        if (!$wilayah) {
            $wilayah = Wilayah::create([
                'nama_wilayah' => 'Indramayu',
                'lat'          => -6.3264,
                'lng'          => 108.3200,
            ]);
        }

        $regionId = $admin->region_id ?? $wilayah->id;
        if (!$admin->region_id) {
            $admin->update(['region_id' => $regionId]);
        }

        // Kategori
        $kategoriDompet     = Kategori::firstOrCreate(['nama_kategori' => 'Dompet']);
        $kategoriElektronik = Kategori::firstOrCreate(['nama_kategori' => 'Elektronik']);
        $kategoriKunci      = Kategori::firstOrCreate(['nama_kategori' => 'Kunci']);

        // ─────────────────────────────────────────────────────────────
        // 2. SKENARIO A (High Match / Cocok)
        // ─────────────────────────────────────────────────────────────
        $this->command?->info('Menyiapkan Skenario A (High Match): Dompet Kulit Eiger...');

        $laporanA = LaporanBarangHilang::updateOrCreate(
            [
                'user_id'     => $user->id,
                'nama_barang' => 'Dompet Kulit Eiger Cokelat Pria',
            ],
            [
                'region_id'            => $regionId,
                'kategori_id'          => $kategoriDompet->id,
                'kategori_barang'      => 'Dompet',
                'warna_barang'         => 'Cokelat Gelap',
                'merek_barang'         => 'Eiger',
                'nomor_seri'           => null,
                'lokasi_hilang'        => 'Kantin Utama Kampus, Meja Tengah',
                'detail_lokasi_hilang' => 'Tertinggal di dekat dispenser air saat jam istirahat',
                'tanggal_hilang'       => '2026-10-01',
                'waktu_hilang'         => '12:30',
                'keterangan'           => 'Dompet kulit lipat warna cokelat gelap hilang saat makan siang. Berisi beberapa kartu penting.',
                'ciri_khusus'          => 'Ada goresan kecil di sudut kanan dan stiker miniatur gunung di dalam.',
                'kontak_pelapor'       => '081234567890',
                'status_laporan'       => WorkflowStatus::REPORT_APPROVED,
                'verified_by_admin_id' => $admin->id,
                'verified_at'          => now(),
                'tampil_di_home'       => false,
                'sumber_laporan'       => 'lapor_hilang',
            ]
        );

        $barangA = Barang::updateOrCreate(
            [
                'admin_id'    => $admin->id,
                'nama_barang' => 'Dompet Pria Warna Coklat',
            ],
            [
                'user_id'                 => $user->id,
                'region_id'               => $regionId,
                'kategori_id'             => $kategoriDompet->id,
                'warna_barang'            => 'Coklat Tua',
                'merek_barang'            => 'Eiger',
                'nomor_seri'              => null,
                'lokasi_ditemukan'        => 'Kantin Utama Kampus',
                'detail_lokasi_ditemukan' => 'Ditemukan di atas bangku kantin setelah jam makan siang',
                'tanggal_ditemukan'       => '2026-10-01',
                'waktu_ditemukan'         => '13:00',
                'deskripsi'               => 'Ditemukan dompet kulit pria warna coklat tua di area kantin. Kondisi bersih dan masih utuh.',
                'ciri_khusus'             => 'Sedikit lecet di pinggir dan stiker alam di selipan kartu.',
                'nama_penemu'             => 'Petugas Kebersihan Kantin',
                'kontak_penemu'           => '081987654321',
                'status_barang'           => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
                'status_laporan'          => WorkflowStatus::REPORT_APPROVED,
                'verified_by_admin_id'    => $admin->id,
                'verified_at'             => now(),
                'tampil_di_home'          => false,
            ]
        );

        $pencocokanA = Pencocokan::updateOrCreate(
            [
                'laporan_hilang_id' => $laporanA->id,
                'barang_id'         => $barangA->id,
            ],
            [
                'admin_id'          => $admin->id,
                'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
                'catatan'           => 'Uji Coba AI Matching: Skenario A (High Match / Cocok)',
                'matched_at'        => now(),
            ]
        );

        $klaimA = Klaim::updateOrCreate(
            [
                'laporan_hilang_id' => $laporanA->id,
                'barang_id'         => $barangA->id,
                'user_id'           => $user->id,
            ],
            [
                'pencocokan_id'         => $pencocokanA->id,
                'admin_id'              => $admin->id,
                'status_klaim'          => WorkflowStatus::CLAIM_LEGACY_PENDING,
                'status_verifikasi'     => WorkflowStatus::CLAIM_UNDER_REVIEW,
                'catatan'               => 'Pengajuan klaim untuk pengujian performa AI Skenario A (High Match).',
                'kontak'                => '081234567890',
                'bukti_ciri_khusus'     => 'Terdapat stiker alam/gunung di saku selipan kartu dan goresan di sudut.',
                'bukti_lokasi_spesifik' => 'Kantin Utama Kampus dekat dispenser air',
                'bukti_waktu_hilang'    => '2026-10-01 12:30',
            ]
        );

        // Eksekusi AI Matching Skenario A
        $this->command?->info('Menjalankan AI Matching Engine untuk Skenario A...');
        $laporanDataA = array_merge($laporanA->toArray(), [
            'nama_kategori' => $laporanA->kategori?->nama_kategori ?? $laporanA->kategori_barang,
        ]);
        $barangDataA = array_merge($barangA->toArray(), [
            'nama_kategori' => $barangA->kategori?->nama_kategori ?? 'Dompet',
        ]);

        $aiResultA = null;
        try {
            $aiResultA = $geminiService->match($laporanDataA, $barangDataA);
        } catch (Throwable $e) {
            $this->command?->warn("  [API Exception Skenario A]: " . $e->getMessage());
        }

        if ($aiResultA && !empty($aiResultA['success'])) {
            $pencocokanA->update([
                'ai_similarity_score' => $aiResultA['similarity_score'],
                'ai_recommendation'   => $aiResultA['recommendation'],
                'ai_reasoning'        => $aiResultA['reasoning'],
                'ai_matched_at'       => now(),
                'ai_model_used'       => $aiResultA['model_used'],
            ]);
            $this->command?->info("  -> AI Skenario A Sukses! Skor: {$aiResultA['similarity_score']} | Rekomendasi: {$aiResultA['recommendation']}");
        } else {
            $errorMsg = $aiResultA['error'] ?? 'API tidak merespons / kuota terbatas';
            $this->command?->warn("  -> AI Skenario A Fallback Digunakan ({$errorMsg})");
            $pencocokanA->update([
                'ai_similarity_score' => 92,
                'ai_recommendation'   => 'high',
                'ai_reasoning'        => 'Simulasi Fallback: Barang memiliki kemiripan sangat tinggi pada merek (Eiger), warna (Cokelat), lokasi (Kantin), dan ciri khusus stiker alam/gunung di selipan kartu.',
                'ai_matched_at'       => now(),
                'ai_model_used'       => 'gemini-fallback-simulated',
            ]);
        }
        $pencocokanA->refresh();

        // ─────────────────────────────────────────────────────────────
        // 3. SKENARIO B (Low Match / Negatif)
        // ─────────────────────────────────────────────────────────────
        $this->command?->info('Menyiapkan Skenario B (Low Match): iPhone vs Kunci Motor...');

        $laporanB = LaporanBarangHilang::updateOrCreate(
            [
                'user_id'     => $user->id,
                'nama_barang' => 'iPhone 13 Midnight 128GB',
            ],
            [
                'region_id'            => $regionId,
                'kategori_id'          => $kategoriElektronik->id,
                'kategori_barang'      => 'Elektronik',
                'warna_barang'         => 'Hitam',
                'merek_barang'         => 'Apple',
                'nomor_seri'           => 'DNQG4XXXXX',
                'lokasi_hilang'        => 'Perpustakaan Pusat Lantai 2',
                'detail_lokasi_hilang' => 'Meja baca individual sisi barat dekat jendela',
                'tanggal_hilang'       => '2026-10-02',
                'waktu_hilang'         => '15:00',
                'keterangan'           => 'iPhone 13 warna midnight tertinggal di atas meja baca lantai 2 perpustakaan.',
                'ciri_khusus'          => 'Casing silikon transparan menguning di bagian tepi.',
                'kontak_pelapor'       => '081234567890',
                'status_laporan'       => WorkflowStatus::REPORT_APPROVED,
                'verified_by_admin_id' => $admin->id,
                'verified_at'          => now(),
                'tampil_di_home'       => false,
                'sumber_laporan'       => 'lapor_hilang',
            ]
        );

        $barangB = Barang::updateOrCreate(
            [
                'admin_id'    => $admin->id,
                'nama_barang' => 'Gantungan Kunci Motor Honda + Remote',
            ],
            [
                'user_id'                 => $user->id,
                'region_id'               => $regionId,
                'kategori_id'             => $kategoriKunci->id,
                'warna_barang'            => 'Hitam Perak',
                'merek_barang'            => 'Honda',
                'nomor_seri'              => null,
                'lokasi_ditemukan'        => 'Parkiran Motor Barat Kampus',
                'detail_lokasi_ditemukan' => 'Tergantung di stang motor dekat pos satpam',
                'tanggal_ditemukan'       => '2026-10-03',
                'waktu_ditemukan'         => '08:15',
                'deskripsi'               => 'Ditemukan satu set gantungan kunci motor Honda beserta remote smart key.',
                'ciri_khusus'             => 'Ada pita merah bertuliskan One Heart.',
                'nama_penemu'             => 'Satpam Parkir Barat',
                'kontak_penemu'           => '081987654322',
                'status_barang'           => WorkflowStatus::FOUND_AVAILABLE,
                'status_laporan'          => WorkflowStatus::REPORT_APPROVED,
                'verified_by_admin_id'    => $admin->id,
                'verified_at'             => now(),
                'tampil_di_home'          => false,
            ]
        );

        $pencocokanB = Pencocokan::updateOrCreate(
            [
                'laporan_hilang_id' => $laporanB->id,
                'barang_id'         => $barangB->id,
            ],
            [
                'admin_id'          => $admin->id,
                'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
                'catatan'           => 'Uji Coba AI Matching: Skenario B (Low Match / Negatif)',
                'matched_at'        => now(),
            ]
        );

        $klaimB = Klaim::updateOrCreate(
            [
                'laporan_hilang_id' => $laporanB->id,
                'barang_id'         => $barangB->id,
                'user_id'           => $user->id,
            ],
            [
                'pencocokan_id'         => $pencocokanB->id,
                'admin_id'              => $admin->id,
                'status_klaim'          => WorkflowStatus::CLAIM_LEGACY_PENDING,
                'status_verifikasi'     => WorkflowStatus::CLAIM_UNDER_REVIEW,
                'catatan'               => 'Pengajuan klaim untuk pengujian performa AI Skenario B (Low Match / Negatif).',
                'kontak'                => '081234567890',
                'bukti_ciri_khusus'     => 'Casing silikon transparan menguning.',
                'bukti_lokasi_spesifik' => 'Perpustakaan Pusat Lantai 2',
                'bukti_waktu_hilang'    => '2026-10-02 15:00',
            ]
        );

        // Eksekusi AI Matching Skenario B
        $this->command?->info('Menjalankan AI Matching Engine untuk Skenario B...');
        $laporanDataB = array_merge($laporanB->toArray(), [
            'nama_kategori' => $laporanB->kategori?->nama_kategori ?? $laporanB->kategori_barang,
        ]);
        $barangDataB = array_merge($barangB->toArray(), [
            'nama_kategori' => $barangB->kategori?->nama_kategori ?? 'Kunci',
        ]);

        $aiResultB = null;
        try {
            $aiResultB = $geminiService->match($laporanDataB, $barangDataB);
        } catch (Throwable $e) {
            $this->command?->warn("  [API Exception Skenario B]: " . $e->getMessage());
        }

        if ($aiResultB && !empty($aiResultB['success'])) {
            $pencocokanB->update([
                'ai_similarity_score' => $aiResultB['similarity_score'],
                'ai_recommendation'   => $aiResultB['recommendation'],
                'ai_reasoning'        => $aiResultB['reasoning'],
                'ai_matched_at'       => now(),
                'ai_model_used'       => $aiResultB['model_used'],
            ]);
            $this->command?->info("  -> AI Skenario B Sukses! Skor: {$aiResultB['similarity_score']} | Rekomendasi: {$aiResultB['recommendation']}");
        } else {
            $errorMsg = $aiResultB['error'] ?? 'API tidak merespons / kuota terbatas';
            $this->command?->warn("  -> AI Skenario B Fallback Digunakan ({$errorMsg})");
            $pencocokanB->update([
                'ai_similarity_score' => 5,
                'ai_recommendation'   => 'uncertain',
                'ai_reasoning'        => 'Simulasi Fallback: Entitas barang sama sekali berbeda (Smartphone Apple iPhone 13 vs Gantungan Kunci Motor Honda). Kategori dan wujud fisik tidak memiliki korelasi.',
                'ai_matched_at'       => now(),
                'ai_model_used'       => 'gemini-fallback-simulated',
            ]);
        }
        $pencocokanB->refresh();

        // ─────────────────────────────────────────────────────────────
        // 4. Output Ringkasan di Terminal
        // ─────────────────────────────────────────────────────────────
        if ($this->command) {
            $headers = [
                'Skenario',
                'Klaim ID',
                'Barang Hilang vs Temuan',
                'Skor AI',
                'Rekomendasi',
                'URL Preview',
            ];

            $urlA = url('/pengelola-barang/verifikasi-klaim/' . $klaimA->id);
            $urlB = url('/pengelola-barang/verifikasi-klaim/' . $klaimB->id);

            $rows = [
                [
                    'Skenario A (High Match)',
                    '#' . $klaimA->id,
                    "{$laporanA->nama_barang}\nvs {$barangA->nama_barang}",
                    ($pencocokanA->ai_similarity_score ?? 0) . '%',
                    strtoupper($pencocokanA->ai_recommendation ?? 'N/A') . ' (' . $pencocokanA->aiRecommendationLabel() . ')',
                    $urlA,
                ],
                [
                    'Skenario B (Low Match)',
                    '#' . $klaimB->id,
                    "{$laporanB->nama_barang}\nvs {$barangB->nama_barang}",
                    ($pencocokanB->ai_similarity_score ?? 0) . '%',
                    strtoupper($pencocokanB->ai_recommendation ?? 'N/A') . ' (' . $pencocokanB->aiRecommendationLabel() . ')',
                    $urlB,
                ],
            ];

            $this->command->newLine();
            $this->command->info('========================================================================================');
            $this->command->info('                   HASIL PENGUJIAN AI MATCHING ENGINE (GEMINI API)                     ');
            $this->command->info('========================================================================================');
            $this->command->table($headers, $rows);
            $this->command->newLine();

            $this->command->line("<fg=cyan;options=bold>Detail Penalaran AI (Reasoning):</>");
            $this->command->line("<fg=green>▶ Skenario A (Score {$pencocokanA->ai_similarity_score}%, Model: {$pencocokanA->ai_model_used}):</>");
            $this->command->line("  \"" . ($pencocokanA->ai_reasoning ?? '-') . "\"");
            $this->command->newLine();
            $this->command->line("<fg=red>▶ Skenario B (Score {$pencocokanB->ai_similarity_score}%, Model: {$pencocokanB->ai_model_used}):</>");
            $this->command->line("  \"" . ($pencocokanB->ai_reasoning ?? '-') . "\"");
            $this->command->newLine();
        }
    }
}
