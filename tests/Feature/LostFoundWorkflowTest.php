<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Admin\Matching\MatchingService;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
            'user_id' => $user->id,
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
            'user_id' => $user->id,
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

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Uji',
            'nama' => 'User Uji',
            'username' => 'user-uji',
            'email' => 'user@example.com',
            'nomor_telepon' => '081111111111',
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
