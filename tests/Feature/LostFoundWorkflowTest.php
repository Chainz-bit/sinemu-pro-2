<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wilayah;
use App\Services\Admin\Matching\MatchingService;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LostFoundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_reports_are_visible_on_home_and_detail_pages(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $approvedLost = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laptop Lenovo',
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Tertinggal di meja baca',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $submittedLost = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Dompet Kulit',
            'lokasi_hilang' => 'Kantin',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Berisi kartu identitas',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        $approvedFound = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Handphone Samsung',
            'deskripsi' => 'Ditemukan di koridor kampus',
            'lokasi_ditemukan' => 'Koridor Kampus',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $submittedFound = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Kunci Motor',
            'deskripsi' => 'Belum diverifikasi',
            'lokasi_ditemukan' => 'Parkiran',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Laptop Lenovo')
            ->assertSee('Handphone Samsung')
            ->assertDontSee('Dompet Kulit')
            ->assertDontSee('Kunci Motor');

        $this->get(route('home.lost-detail', $approvedLost))->assertOk();
        $this->get(route('home.found-detail', $approvedFound))->assertOk();
        $this->get(route('home.lost-detail', $submittedLost))->assertNotFound();
        $this->get(route('home.found-detail', $submittedFound))->assertNotFound();
    }

    public function test_admin_and_user_can_complete_the_new_matching_and_claim_workflow(): void
    {
        $user = $this->createUser();
        $finder = $this->createUser('workflow-finder@example.com', 'workflow-finder', '081111111112');
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Tablet Xiaomi',
            'lokasi_hilang' => 'Lab Komputer',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang setelah praktikum',
            'kontak_pelapor' => '081234567890',
            'bukti_kepemilikan' => 'Wallpaper keluarga dan goresan kecil di sudut',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $finder->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Tablet Xiaomi',
            'deskripsi' => 'Ditemukan di meja belakang',
            'lokasi_ditemukan' => 'Lab Komputer',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.verify', $lostReport), [
                'status_laporan' => 'approved',
            ])
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.verify', $foundItem), [
                'status_laporan' => 'approved',
            ])
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Ciri dan lokasi sangat mirip',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pencocokans', [
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
        ]);

        $pencocokan = Pencocokan::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('user.claims.store'), [
                'barang_id' => $foundItem->id,
                'laporan_hilang_id' => $lostReport->id,
                'kontak_pelapor' => '081234567890',
                'bukti_kepemilikan' => 'Wallpaper keluarga dan goresan kecil di sudut',
                'bukti_ciri_khusus' => 'Ada goresan kecil di sudut kanan atas',
                'bukti_detail_isi' => 'Terpasang casing abu-abu',
                'bukti_lokasi_spesifik' => 'Meja belakang dekat colokan listrik',
                'bukti_waktu_hilang' => '10:30',
                'bukti_foto' => [UploadedFile::fake()->create('bukti-1.jpg', 128, 'image/jpeg')],
                'persetujuan_klaim' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('klaims', [
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'pencocokan_id' => $pencocokan->id,
            'status_klaim' => 'pending',
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
        ]);

        $claim = \App\Models\Klaim::query()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.claim-verifications.approve', $claim), [
                'identitas_pelapor_valid' => '1',
                'detail_barang_valid' => '1',
                'kronologi_valid' => '1',
                'bukti_visual_valid' => '1',
                'kecocokan_data_laporan' => '1',
                'catatan_verifikasi_admin' => 'Bukti kuat dan konsisten.',
            ])
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.claim-verifications.complete', $claim))
            ->assertRedirect();

        $this->assertDatabaseHas('klaims', [
            'id' => $claim->id,
            'status_klaim' => 'disetujui',
            'status_verifikasi' => WorkflowStatus::CLAIM_COMPLETED,
            'skor_validitas' => 100,
        ]);
        $this->assertDatabaseHas('barangs', [
            'id' => $foundItem->id,
            'status_barang' => 'sudah_dikembalikan',
            'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
            'tampil_di_home' => false,
        ]);
        $this->assertDatabaseHas('laporan_barang_hilangs', [
            'id' => $lostReport->id,
            'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
            'tampil_di_home' => false,
        ]);
        $this->assertDatabaseHas('pencocokans', [
            'id' => $pencocokan->id,
            'status_pencocokan' => WorkflowStatus::MATCH_COMPLETED,
        ]);
    }

    public function test_admin_cannot_create_second_active_match_for_same_lost_report_or_found_item(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReportA = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laptop A',
            'lokasi_hilang' => 'Ruang A',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Hilang di ruang A',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);
        $lostReportB = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laptop B',
            'lokasi_hilang' => 'Ruang B',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Hilang di ruang B',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $foundItemA = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Laptop Temuan A',
            'deskripsi' => 'Ditemukan di ruang A',
            'lokasi_ditemukan' => 'Ruang A',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);
        $foundItemB = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Laptop Temuan B',
            'deskripsi' => 'Ditemukan di ruang B',
            'lokasi_ditemukan' => 'Ruang B',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReportA->id,
                'barang_id' => $foundItemA->id,
                'catatan' => 'Match pertama aktif',
            ])
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReportA->id,
                'barang_id' => $foundItemB->id,
                'catatan' => 'Harus ditolak karena laporan sudah punya match aktif',
            ])
            ->assertSessionHas('error');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReportB->id,
                'barang_id' => $foundItemA->id,
                'catatan' => 'Harus ditolak karena barang sudah punya match aktif',
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, Pencocokan::query()->count());
    }

    public function test_user_cannot_claim_found_item_that_is_not_available(): void
    {
        $user = $this->createUser();
        $finder = $this->createUser('not-available-finder@example.com', 'not-available-finder', '081111111113');
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Tablet Tidak Tersedia',
            'lokasi_hilang' => 'Lab Komputer',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang setelah praktikum',
            'kontak_pelapor' => '081234567890',
            'bukti_kepemilikan' => 'Nomor seri perangkat',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $finder->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Tablet Tidak Tersedia',
            'deskripsi' => 'Ditemukan di meja belakang',
            'lokasi_ditemukan' => 'Lab Komputer',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIMED,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        Pencocokan::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'matched_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('user.claims.store'), [
                'barang_id' => $foundItem->id,
                'laporan_hilang_id' => $lostReport->id,
                'kontak_pelapor' => '081234567890',
                'bukti_kepemilikan' => 'Nomor seri perangkat',
                'bukti_ciri_khusus' => 'Ada stiker kecil',
                'bukti_detail_isi' => 'Casing abu-abu',
                'bukti_lokasi_spesifik' => 'Meja belakang dekat colokan listrik',
                'bukti_waktu_hilang' => '10:30',
                'bukti_foto' => [UploadedFile::fake()->create('bukti-claim.jpg', 128, 'image/jpeg')],
                'persetujuan_klaim' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Barang ini sedang tidak tersedia untuk diklaim.');

        $this->assertDatabaseMissing('klaims', [
            'barang_id' => $foundItem->id,
            'laporan_hilang_id' => $lostReport->id,
        ]);
    }

    public function test_claim_form_allows_available_found_item_without_lost_report(): void
    {
        $user = $this->createUser();
        $finder = $this->createUser('form-finder@example.com', 'form-finder', '081111111114');
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $foundItem = $this->createApprovedFoundItemForMatching($admin, $finder, $kategori, (int) $admin->region_id);

        $response = $this->actingAs($user)
            ->get(route('user.claims.create', ['barang_id' => $foundItem->id]));

        $response->assertOk()
            ->assertSee('Jika Anda belum membuat laporan kehilangan, Anda tetap bisa mengajukan klaim dengan bukti kepemilikan yang jelas.', false)
            ->assertSee('Tidak menggunakan laporan hilang', false)
            ->assertSee($foundItem->nama_barang, false)
            ->assertDontSee('Belum ada data yang bisa diklaim', false);

        $this->assertSame($foundItem->id, $response->viewData('selectedBarangId'));
        $this->assertCount(1, $response->viewData('foundItems'));
        $this->assertCount(0, $response->viewData('claimableLostReports'));
    }

    public function test_user_can_claim_found_item_without_lost_report_with_complete_proof(): void
    {
        Storage::fake('local');

        $user = $this->createUser();
        $finder = $this->createUser('direct-claim-finder@example.com', 'direct-claim-finder', '081111111116');
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $foundItem = $this->createApprovedFoundItemForMatching($admin, $finder, $kategori, (int) $admin->region_id);
        $notificationsBefore = AdminNotification::query()->count();

        $this->actingAs($user)
            ->post(route('user.claims.store'), [
                'barang_id' => $foundItem->id,
                'kontak_pelapor' => '081234567890',
                'bukti_kepemilikan' => 'Saya memiliki nota dan nomor seri barang.',
                'bukti_ciri_khusus' => 'Ada stiker kecil di bagian belakang.',
                'bukti_detail_isi' => 'Kondisi terakhir memakai casing hitam.',
                'bukti_lokasi_spesifik' => 'Ruang baca dekat jendela.',
                'bukti_waktu_hilang' => '09:45',
                'bukti_foto' => [UploadedFile::fake()->create('bukti-direct.jpg', 128, 'image/jpeg')],
                'persetujuan_klaim' => '1',
            ])
            ->assertRedirect(route('user.claim-history'))
            ->assertSessionHas('status', 'Pengajuan klaim berhasil dikirim. Pantau status verifikasi di Riwayat Klaim.');

        $claim = Klaim::query()->sole();
        $proofPath = $claim->bukti_foto[0] ?? '';

        $this->assertNull($claim->laporan_hilang_id);
        $this->assertNull($claim->pencocokan_id);
        $this->assertSame($foundItem->id, $claim->barang_id);
        $this->assertSame($user->id, $claim->user_id);
        $this->assertSame($admin->id, $claim->admin_id);
        $this->assertSame('081234567890', $claim->kontak);
        $this->assertSame('Saya memiliki nota dan nomor seri barang.', $claim->bukti_kepemilikan);
        Storage::disk('local')->assertExists($proofPath);
        $this->assertSame($notificationsBefore + 1, AdminNotification::query()->count());
        $this->assertDatabaseHas('admin_notifications', [
            'admin_id' => $admin->id,
            'type' => 'klaim_baru',
        ]);
        $this->assertDatabaseHas('barangs', [
            'id' => $foundItem->id,
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
        ]);
    }

    public function test_user_cannot_submit_duplicate_active_claim_when_item_status_is_stale(): void
    {
        Storage::fake('local');

        $user = $this->createUser();
        $finder = $this->createUser('duplicate-finder@example.com', 'duplicate-finder', '081111111117');
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $lostReport = $this->createApprovedLostReportForMatching($user, $admin, (int) $admin->region_id);
        $foundItem = $this->createApprovedFoundItemForMatching($admin, $finder, $kategori, (int) $admin->region_id);
        $match = Pencocokan::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'matched_at' => now(),
        ]);
        Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'pencocokan_id' => $match->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
        ]);
        $notificationsBefore = AdminNotification::query()->count();

        $this->actingAs($user)
            ->post(route('user.claims.store'), [
                'barang_id' => $foundItem->id,
                'laporan_hilang_id' => $lostReport->id,
                'kontak_pelapor' => '081234567890',
                'bukti_kepemilikan' => 'Nomor seri perangkat',
                'bukti_ciri_khusus' => 'Ada stiker kecil',
                'bukti_detail_isi' => 'Casing abu-abu',
                'bukti_lokasi_spesifik' => 'Meja belakang dekat colokan listrik',
                'bukti_waktu_hilang' => '10:30',
                'bukti_foto' => [UploadedFile::fake()->create('bukti-duplicate.jpg', 128, 'image/jpeg')],
                'persetujuan_klaim' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Anda sudah pernah mengajukan klaim aktif untuk barang ini.');

        $this->assertSame(1, Klaim::query()->count());
        $this->assertSame($notificationsBefore, AdminNotification::query()->count());
        $this->assertCount(0, Storage::disk('local')->allFiles('private/verifikasi-klaim'));
    }

    public function test_admin_can_mark_candidate_as_not_matching_and_candidate_is_not_suggested_again(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laptop Asus',
            'kategori_barang' => 'Elektronik',
            'warna_barang' => 'Hitam',
            'lokasi_hilang' => 'Ruang Dosen',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang saat rapat',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Laptop Asus',
            'warna_barang' => 'Hitam',
            'deskripsi' => 'Ditemukan di ruang dosen',
            'lokasi_ditemukan' => 'Ruang Dosen',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.dismiss'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Tidak cocok setelah ditinjau admin',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('pencocokans', [
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CANCELLED,
        ]);

        $candidates = $this->app
            ->make(MatchingService::class)
            ->findCandidatesForLostReport($lostReport->fresh());

        $this->assertCount(0, $candidates);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items.show', $lostReport))
            ->assertOk()
            ->assertSee('Belum ada kandidat dengan skor kecocokan yang cukup atau semua kandidat sudah ditinjau.');
    }

    public function test_admin_can_review_candidates_from_found_detail_and_confirm_match(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'iPad Air',
            'kategori_barang' => 'Elektronik',
            'warna_barang' => 'Silver',
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Tertinggal di meja baca',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'iPad Air',
            'warna_barang' => 'Silver',
            'deskripsi' => 'Ditemukan di area perpustakaan',
            'lokasi_ditemukan' => 'Perpustakaan',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items.show', $foundItem))
            ->assertOk()
            ->assertSee('Kandidat Laporan Barang Hilang')
            ->assertSee($lostReport->nama_barang);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Dikonfirmasi dari halaman detail barang temuan',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('pencocokans', [
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
        ]);
        $this->assertDatabaseHas('laporan_barang_hilangs', [
            'id' => $lostReport->id,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
        ]);
        $this->assertDatabaseHas('barangs', [
            'id' => $foundItem->id,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
        ]);
    }

    public function test_admin_cannot_confirm_match_for_reports_outside_region(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $otherRegion = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Luar',
            'lat' => -6.4,
            'lng' => 108.4,
        ]);
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $otherRegion->id,
            'nama_barang' => 'Laptop Luar Wilayah',
            'lokasi_hilang' => 'Gedung Lain',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang di luar wilayah admin',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $otherRegion->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Laptop Luar Wilayah',
            'deskripsi' => 'Ditemukan di luar wilayah admin',
            'lokasi_ditemukan' => 'Gedung Lain',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Harus ditolak karena beda wilayah',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('pencocokans', 0);
    }

    public function test_admin_cannot_confirm_match_when_lost_report_region_is_null(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = $this->createApprovedLostReportForMatching($user, $admin, null);
        $foundItem = $this->createApprovedFoundItemForMatching($admin, $user, $kategori, (int) $admin->region_id);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Region laporan kosong harus ditolak',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('pencocokans', 0);
    }

    public function test_admin_cannot_confirm_match_when_found_item_region_is_null(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = $this->createApprovedLostReportForMatching($user, $admin, (int) $admin->region_id);
        $foundItem = $this->createApprovedFoundItemForMatching($admin, $user, $kategori, null);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Region barang kosong harus ditolak',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('pencocokans', 0);
    }

    public function test_admin_cannot_confirm_match_when_both_regions_are_null(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = $this->createApprovedLostReportForMatching($user, $admin, null);
        $foundItem = $this->createApprovedFoundItemForMatching($admin, $user, $kategori, null);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Kedua region kosong harus ditolak',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('pencocokans', 0);
    }

    public function test_admin_cannot_confirm_match_when_pair_region_belongs_to_other_region(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $otherRegion = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Pair Lain',
            'lat' => -6.41,
            'lng' => 108.41,
        ]);
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = $this->createApprovedLostReportForMatching($user, $admin, (int) $otherRegion->id);
        $foundItem = $this->createApprovedFoundItemForMatching($admin, $user, $kategori, (int) $otherRegion->id);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Pair wilayah lain harus ditolak',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('pencocokans', 0);
    }

    public function test_admin_can_confirm_match_when_all_regions_match(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = $this->createApprovedLostReportForMatching($user, $admin, (int) $admin->region_id);
        $foundItem = $this->createApprovedFoundItemForMatching($admin, $user, $kategori, (int) $admin->region_id);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Semua region sama',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('pencocokans', [
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
        ]);
    }

    public function test_confirming_same_match_twice_does_not_duplicate_notifications(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = $this->createApprovedLostReportForMatching($user, $admin, (int) $admin->region_id);
        $foundItem = $this->createApprovedFoundItemForMatching($admin, $user, $kategori, (int) $admin->region_id);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Konfirmasi pertama',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Pencocokan berhasil ditandai dan notifikasi telah dikirim.');

        $this->assertSame(
            1,
            UserNotification::query()
                ->where('user_id', $user->id)
                ->where('type', 'pencocokan_ditemukan')
                ->count()
        );

        $this->actingAs($admin, 'admin')
            ->post(route('admin.matches.store'), [
                'laporan_hilang_id' => $lostReport->id,
                'barang_id' => $foundItem->id,
                'catatan' => 'Konfirmasi ulang pasangan yang sama',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Pencocokan sudah dikonfirmasi.');

        $this->assertSame(
            1,
            UserNotification::query()
                ->where('user_id', $user->id)
                ->where('type', 'pencocokan_ditemukan')
                ->count()
        );
    }

    private function createApprovedLostReportForMatching(User $user, Admin $admin, ?int $regionId): LaporanBarangHilang
    {
        return LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $regionId,
            'nama_barang' => 'Barang Hilang Scope',
            'lokasi_hilang' => 'Lokasi Hilang Scope',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Laporan untuk uji scope matching',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);
    }

    private function createApprovedFoundItemForMatching(Admin $admin, User $user, Kategori $kategori, ?int $regionId): Barang
    {
        return Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $regionId,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Barang Temuan Scope',
            'deskripsi' => 'Barang temuan untuk uji scope matching',
            'lokasi_ditemukan' => 'Lokasi Temuan Scope',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);
    }

    private function createUser(
        string $email = 'user@example.com',
        string $username = 'user-uji',
        string $phone = '081111111111'
    ): User
    {
        $user = User::query()->create([
            'name' => 'User Uji',
            'nama' => 'User Uji',
            'username' => $username,
            'email' => $email,
            'nomor_telepon' => $phone,
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin',
            'email' => 'super@example.com',
            'username' => 'super-admin',
            'password' => Hash::make('password123'),
        ]);
        $region = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Admin Uji',
            'lat' => -6.326,
            'lng' => 108.32,
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin Uji',
            'email' => 'admin@example.com',
            'username' => 'admin-uji',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Testing No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
