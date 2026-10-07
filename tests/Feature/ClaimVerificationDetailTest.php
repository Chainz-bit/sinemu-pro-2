<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Klaim;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaimVerificationDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_verification_detail_shows_claim_summary_and_statuses(): void
    {
        $admin = $this->createAdmin('detail-admin@example.com', 'detail-admin');
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Kamera Sony',
            'lokasi_hilang' => 'Studio Foto',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang setelah sesi foto',
            'ciri_khusus' => 'Ada stiker kecil di sisi samping',
            'bukti_kepemilikan' => 'Nomor seri dan tas kamera asli',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Kamera Sony',
            'deskripsi' => 'Ditemukan di studio foto',
            'lokasi_ditemukan' => 'Studio Foto',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_RETURNED,
            'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
            'tampil_di_home' => false,
        ]);

        $claim = Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'status_verifikasi' => WorkflowStatus::CLAIM_COMPLETED,
            'catatan' => 'Pengaju membawa bukti kepemilikan lengkap.',
            'catatan_verifikasi_admin' => 'Barang sudah diserahkan langsung.',
            'bukti_ciri_khusus' => 'Ada stiker kecil di sisi samping',
            'bukti_detail_isi' => 'Tas kamera dan baterai cadangan',
            'bukti_lokasi_spesifik' => 'Dekat meja pencahayaan',
            'bukti_waktu_hilang' => '15:30',
            'skor_validitas' => 95,
            'hasil_checklist' => [
                'identitas_pelapor_valid' => true,
                'detail_barang_valid' => true,
                'kronologi_valid' => true,
                'bukti_visual_valid' => true,
                'kecocokan_data_laporan' => true,
            ],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.claim-verifications.show', $claim));

        $response->assertOk();
        $response->assertSee('Detail Verifikasi Klaim');
        $response->assertSee('Kamera Sony');
        $response->assertSee('SELESAI');
        $response->assertSee('Studio Foto');
        $response->assertSee('Pengaju membawa bukti kepemilikan lengkap.');
        $response->assertSee('Barang sudah diserahkan langsung.');
        $response->assertSee('Ada stiker kecil di sisi samping');
    }

    public function test_claim_verification_detail_for_other_admin_claim_returns_forbidden(): void
    {
        $ownerAdmin = $this->createAdmin('owner-admin@example.com', 'owner-admin');
        $otherAdmin = $this->createAdmin('other-admin@example.com', 'other-admin');
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Aksesoris']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Jam Tangan',
            'lokasi_hilang' => 'Lobby',
            'tanggal_hilang' => now()->subDays(2)->toDateString(),
            'keterangan' => 'Hilang saat menunggu teman',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $ownerAdmin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Jam Tangan',
            'deskripsi' => 'Ditemukan di kursi lobby',
            'lokasi_ditemukan' => 'Lobby',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $claim = Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $ownerAdmin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Masih diverifikasi',
        ]);

        $this->actingAs($otherAdmin, 'admin')
            ->get(route('admin.claim-verifications.show', $claim))
            ->assertForbidden();
    }

    public function test_claim_without_pencocokan_auto_links_and_renders_ai_panel_with_action_button(): void
    {
        $admin = $this->createAdmin('autolink-admin@example.com', 'autolink-admin');
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Dompet']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Dompet Kulit',
            'deskripsi' => 'Ditemukan di kantin',
            'lokasi_ditemukan' => 'Kantin',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        // Klaim mandiri tanpa laporan hilang dan tanpa pencocokan_id (persis seperti klaim 3)
        $claim = Klaim::query()->create([
            'laporan_hilang_id' => null,
            'barang_id' => $foundItem->id,
            'pencocokan_id' => null,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Klaim bukti mandiri',
            'bukti_kepemilikan' => 'Ada struk pembelian di dalam',
            'bukti_ciri_khusus' => 'Goresan di sudut kanan',
        ]);

        $this->assertNull($claim->pencocokan_id);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.claim-verifications.show', $claim));

        $response->assertOk();
        $response->assertSee('Analisis AI');
        $response->assertSee('Jalankan Analisis AI Sekarang');
        $response->assertDontSee('Belum ada data pencocokan resmi yang terhubung ke klaim ini.');

        $claim->refresh();
        $this->assertNotNull($claim->pencocokan_id);
        $this->assertDatabaseHas('pencocokans', [
            'id' => $claim->pencocokan_id,
            'laporan_hilang_id' => null,
            'barang_id' => $foundItem->id,
        ]);
    }

    public function test_run_ai_analysis_endpoint_returns_json_and_saves_score(): void
    {
        $admin = $this->createAdmin('ai-test-admin@example.com', 'ai-test-admin');
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Kunci']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Kunci Motor Honda',
            'deskripsi' => 'Ditemukan di parkiran motor',
            'lokasi_ditemukan' => 'Parkiran Motor',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $claim = Klaim::query()->create([
            'laporan_hilang_id' => null,
            'barang_id' => $foundItem->id,
            'pencocokan_id' => null,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'bukti_kepemilikan' => 'Ada gantungan berlogo Honda',
            'bukti_ciri_khusus' => 'Gantungan karet merah',
        ]);

        $mockService = $this->createMock(\App\Services\GeminiMatchingService::class);
        $mockService->method('match')->willReturn([
            'success' => true,
            'similarity_score' => 88,
            'recommendation' => 'high',
            'reasoning' => 'Kunci dan gantungan sangat identik dengan bukti kepemilikan.',
            'model_used' => 'gemini-3.6-flash',
            'error' => null,
        ]);
        $this->app->instance(\App\Services\GeminiMatchingService::class, $mockService);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.claim-verifications.run-ai-analysis', $claim), [], [
                'Accept' => 'application/json',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.similarity_score', 88)
            ->assertJsonPath('data.recommendation', 'high')
            ->assertJsonPath('data.reasoning', 'Kunci dan gantungan sangat identik dengan bukti kepemilikan.');

        $claim->refresh();
        $this->assertNotNull($claim->pencocokan_id);
        $this->assertDatabaseHas('pencocokans', [
            'id' => $claim->pencocokan_id,
            'ai_similarity_score' => 88,
            'ai_recommendation' => 'high',
            'ai_reasoning' => 'Kunci dan gantungan sangat identik dengan bukti kepemilikan.',
        ]);
    }

    public function test_approve_claim_fails_when_ai_score_is_below_75(): void
    {
        $admin = $this->createAdmin('low-ai-admin@example.com', 'low-ai-admin');
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Flashdisk 32GB',
            'deskripsi' => 'Ditemukan di lab',
            'lokasi_ditemukan' => 'Lab Komputer',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $pencocokan = \App\Models\Pencocokan::query()->create([
            'barang_id' => $foundItem->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => 'pending',
            'ai_similarity_score' => 35,
            'ai_recommendation' => 'low',
            'ai_reasoning' => 'Visual mirip namun data tidak cocok.',
        ]);

        $claim = Klaim::query()->create([
            'barang_id' => $foundItem->id,
            'pencocokan_id' => $pencocokan->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Klaim flashdisk',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.claim-verifications.approve', $claim), [
                'identitas_pelapor_valid' => '1',
                'detail_barang_valid' => '1',
                'kronologi_valid' => '1',
                'bukti_visual_valid' => '1',
                'kecocokan_data_laporan' => '1',
                'catatan_verifikasi_admin' => 'Mencoba approve skor rendah.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Klaim tidak dapat disetujui otomatis karena skor AI di bawah 75.');

        $claim->refresh();
        $this->assertSame(WorkflowStatus::CLAIM_UNDER_REVIEW, $claim->status_verifikasi);
    }

    public function test_approve_claim_fails_when_any_checklist_item_is_no(): void
    {
        $admin = $this->createAdmin('any-no-admin@example.com', 'any-no-admin');
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Dokumen']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'KTM',
            'deskripsi' => 'Ditemukan di perpustakaan',
            'lokasi_ditemukan' => 'Perpustakaan',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $pencocokan = \App\Models\Pencocokan::query()->create([
            'barang_id' => $foundItem->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => 'pending',
            'ai_similarity_score' => 85,
            'ai_recommendation' => 'high',
        ]);

        $claim = Klaim::query()->create([
            'barang_id' => $foundItem->id,
            'pencocokan_id' => $pencocokan->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.claim-verifications.approve', $claim), [
                'identitas_pelapor_valid' => '1',
                'detail_barang_valid' => '0', // Satu poin "Tidak"
                'kronologi_valid' => '1',
                'bukti_visual_valid' => '1',
                'kecocokan_data_laporan' => '1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Klaim tidak dapat disetujui karena ada poin checklist bernilai "Tidak".');

        $claim->refresh();
        $this->assertSame(WorkflowStatus::CLAIM_UNDER_REVIEW, $claim->status_verifikasi);
    }

    public function test_reject_claim_requires_alasan_penolakan(): void
    {
        $admin = $this->createAdmin('reject-admin@example.com', 'reject-admin');
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Tas']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Ransel Hitam',
            'deskripsi' => 'Ditemukan di kantin',
            'lokasi_ditemukan' => 'Kantin',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $claim = Klaim::query()->create([
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.claim-verifications.reject', $claim), [
                'identitas_pelapor_valid' => '1',
                'detail_barang_valid' => '0',
                'kronologi_valid' => '0',
                'bukti_visual_valid' => '0',
                'kecocokan_data_laporan' => '0',
                'alasan_penolakan' => '', // Kosong
            ]);

        $response->assertSessionHasErrors(['alasan_penolakan']);
    }

    public function test_reject_claim_with_direct_pencocokan_succeeds_and_auto_assigns_admin(): void
    {
        $admin = $this->createAdmin('reject-success-admin@example.com', 'reject-success-admin');
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Charger Laptop',
            'deskripsi' => 'Ditemukan di kelas',
            'lokasi_ditemukan' => 'Kelas A',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $pencocokan = \App\Models\Pencocokan::query()->create([
            'barang_id' => $foundItem->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => 'pending',
            'ai_similarity_score' => 30,
        ]);

        $claim = Klaim::query()->create([
            'barang_id' => $foundItem->id,
            'pencocokan_id' => $pencocokan->id,
            'laporan_hilang_id' => null,
            'user_id' => $user->id,
            'admin_id' => null, // Klaim belum memiliki penugasan admin (harus auto-assign)
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.claim-verifications.reject', $claim), [
                'identitas_pelapor_valid' => '1',
                'detail_barang_valid' => '0',
                'kronologi_valid' => '0',
                'bukti_visual_valid' => '0',
                'kecocokan_data_laporan' => '0',
                'alasan_penolakan' => 'Barang tidak sesuai ciri pengaju.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Klaim berhasil ditolak.');

        $claim->refresh();
        $this->assertSame(WorkflowStatus::CLAIM_REJECTED, $claim->status_verifikasi);
        $this->assertSame((int) $admin->id, (int) $claim->admin_id);
        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $claim->barang->status_barang);
        $this->assertSame(WorkflowStatus::MATCH_CLAIM_REJECTED, $claim->pencocokan->status_pencocokan);
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Detail Klaim',
            'nama' => 'User Detail Klaim',
            'username' => 'user-detail-klaim',
            'email' => 'claim-detail-user@example.com',
            'nomor_telepon' => '081111111116',
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(string $email, string $username): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin ' . $username,
            'email' => 'super-' . $email,
            'username' => 'super-' . $username,
            'password' => Hash::make('password123'),
        ]);

        $region = Wilayah::query()->create(['nama_wilayah' => 'Wilayah ' . $username]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin ' . $username,
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Detail Klaim No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
