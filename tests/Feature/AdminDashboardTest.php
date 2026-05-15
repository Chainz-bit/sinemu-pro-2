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

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_stats_and_combined_latest_reports(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laptop Lenovo',
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => now()->subDays(3)->toDateString(),
            'keterangan' => 'Tertinggal di meja baca',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'updated_at' => now()->subHours(3),
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Tas Biru',
            'deskripsi' => 'Ditemukan di kursi ruang tunggu',
            'lokasi_ditemukan' => 'Lobby',
            'tanggal_ditemukan' => now()->subDays(2)->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'updated_at' => now()->subHours(2),
        ]);

        $claimLostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'HP Samsung',
            'lokasi_hilang' => 'Kantin',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang saat makan siang',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
            'updated_at' => now()->subHour(),
        ]);

        $claimFoundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'HP Samsung',
            'deskripsi' => 'Ditemukan dekat kasir',
            'lokasi_ditemukan' => 'Kantin',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => 'dalam_proses_klaim',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
            'updated_at' => now()->subMinutes(30),
        ]);

        Klaim::query()->create([
            'laporan_hilang_id' => $claimLostReport->id,
            'barang_id' => $claimFoundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => 'pending',
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Bukti sedang diperiksa',
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti-1.jpg'],
            'bukti_ciri_khusus' => 'Ada goresan di sisi kanan',
            'bukti_lokasi_spesifik' => 'Dekat kasir',
            'bukti_waktu_hilang' => '12:30:00',
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Laptop Lenovo');
        $response->assertSee('Tas Biru');
        $response->assertSee('HP Samsung');
        $response->assertSee('3', false);
        $response->assertSee('2', false);
        $response->assertSee('1', false);

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertTrue(
            strpos($content, 'HP Samsung') < strpos($content, 'Tas Biru')
            && strpos($content, 'Tas Biru') < strpos($content, 'Laptop Lenovo')
        );

        $this->assertSame(2, LaporanBarangHilang::query()->where('sumber_laporan', 'lapor_hilang')->count());
        $this->assertSame(2, Barang::query()->count());
        $this->assertSame(1, Klaim::query()->where('status_verifikasi', WorkflowStatus::CLAIM_UNDER_REVIEW)->count());
        $this->assertSame('Lobby', $foundItem->fresh()?->lokasi_ditemukan);
    }

    public function test_admin_dashboard_applies_search_and_status_filters(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Dokumen Penting',
            'lokasi_hilang' => 'Ruang Administrasi',
            'tanggal_hilang' => now()->subDays(2)->toDateString(),
            'keterangan' => 'Map merah',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Payung Hitam',
            'deskripsi' => 'Ditemukan di parkiran',
            'lokasi_ditemukan' => 'Parkiran',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Tablet Xiaomi',
            'lokasi_hilang' => 'Lab Komputer',
            'tanggal_hilang' => now()->subDays(3)->toDateString(),
            'keterangan' => 'Hilang setelah kelas',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
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
            'tanggal_ditemukan' => now()->subDays(2)->toDateString(),
            'status_barang' => 'dalam_proses_klaim',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => 'disetujui',
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
            'catatan' => 'Klaim disetujui pengelola barang',
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti-2.jpg'],
            'bukti_ciri_khusus' => 'Ada casing abu-abu',
            'bukti_lokasi_spesifik' => 'Meja belakang',
            'bukti_waktu_hilang' => '09:00:00',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard', [
            'search' => 'Tablet Xiaomi',
            'status' => 'diproses',
        ]));

        $response->assertOk();
        $response->assertSee('Tablet Xiaomi');

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $tableBody = $this->extractTableBody($content);

        $this->assertStringContainsString('Tablet Xiaomi', $tableBody);
        $this->assertStringNotContainsString('Dokumen Penting', $tableBody);
        $this->assertStringNotContainsString('Payung Hitam', $tableBody);
        $this->assertStringContainsString('DISETUJUI', $tableBody);
    }

    public function test_dashboard_report_update_validates_payload_by_type(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Dompet Kulit',
            'deskripsi' => 'Ditemukan di ruang rapat',
            'lokasi_ditemukan' => 'Ruang Rapat',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $response = $this->from(route('admin.dashboard'))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.dashboard.reports.update', ['type' => 'temuan', 'id' => $foundItem->id]), [
                'lokasi_ditemukan' => 'Lobi Utama',
                'tanggal_ditemukan' => now()->toDateString(),
            ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasErrors('nama_barang');
        $this->assertSame('Dompet Kulit', $foundItem->fresh()?->nama_barang);
    }

    public function test_dashboard_publish_home_validates_route_type(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Aksesoris']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Kunci Motor',
            'deskripsi' => 'Ditemukan di parkiran timur',
            'lokasi_ditemukan' => 'Parkiran Timur',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $response = $this->from(route('admin.dashboard'))
            ->actingAs($admin, 'admin')
            ->post(route('admin.dashboard.reports.publish-home', ['type' => 'invalid-type', 'id' => $foundItem->id]));

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasErrors('type');
        $this->assertFalse((bool) $foundItem->fresh()?->tampil_di_home);
    }

    public function test_dashboard_quick_actions_cannot_update_lost_reports_outside_region(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $adminRegion = \App\Models\Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Dashboard Admin',
            'lat' => -6.32,
            'lng' => 108.32,
        ]);
        $otherRegion = \App\Models\Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Dashboard Lain',
            'lat' => -6.42,
            'lng' => 108.42,
        ]);
        $admin->forceFill(['region_id' => $adminRegion->id])->save();

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $otherRegion->id,
            'nama_barang' => 'Dokumen Luar Wilayah',
            'lokasi_hilang' => 'Kantor Lain',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Tidak boleh diubah admin wilayah ini',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.dashboard.reports.update', ['type' => 'hilang', 'id' => $lostReport->id]), [
                'nama_barang' => 'Dokumen Diubah',
                'lokasi_hilang' => 'Lokasi Diubah',
                'tanggal_hilang' => now()->toDateString(),
                'keterangan' => 'Percobaan ubah lintas wilayah',
            ])
            ->assertForbidden();

        $this->assertSame('Dokumen Luar Wilayah', $lostReport->fresh()?->nama_barang);
    }

    public function test_active_admin_without_region_sees_empty_dashboard_scope(): void
    {
        $admin = $this->createAdmin();
        $admin->forceFill(['region_id' => null])->save();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $region = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Data Tidak Boleh Terlihat',
            'lat' => -6.45,
            'lng' => 108.45,
        ]);
        $otherAdmin = $this->createScopedAdmin($region, 'dashboard-other-region');

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
            'nama_barang' => 'Laporan Tidak Terlihat',
            'lokasi_hilang' => 'Wilayah lain',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Pengelola tanpa wilayah tidak boleh melihat ini',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $otherAdmin->id,
            'region_id' => $region->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Barang Tidak Terlihat',
            'deskripsi' => 'Pengelola tanpa wilayah tidak boleh melihat ini',
            'lokasi_ditemukan' => 'Wilayah lain',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => 'pending',
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti-scope.jpg'],
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalHilang', 0);
        $response->assertViewHas('totalTemuan', 0);
        $response->assertViewHas('menungguVerifikasi', 0);
        $response->assertDontSee('Laporan Tidak Terlihat');
        $response->assertDontSee('Barang Tidak Terlihat');
    }

    public function test_admin_dashboard_statistics_are_scoped_to_region(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $otherRegion = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Dashboard Statistik Lain',
            'lat' => -6.47,
            'lng' => 108.47,
        ]);
        $otherAdmin = $this->createScopedAdmin($otherRegion, 'dashboard-stat-other');

        $ownLostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laporan Wilayah Sendiri',
            'lokasi_hilang' => 'Wilayah sendiri',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Masuk statistik',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $ownFoundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Barang Wilayah Sendiri',
            'deskripsi' => 'Masuk statistik',
            'lokasi_ditemukan' => 'Wilayah sendiri',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $otherRegion->id,
            'nama_barang' => 'Laporan Wilayah Lain',
            'lokasi_hilang' => 'Wilayah lain',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Tidak masuk statistik',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        Barang::query()->create([
            'admin_id' => $otherAdmin->id,
            'region_id' => $otherRegion->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Barang Wilayah Lain',
            'deskripsi' => 'Tidak masuk statistik',
            'lokasi_ditemukan' => 'Wilayah lain',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        Klaim::query()->create([
            'laporan_hilang_id' => $ownLostReport->id,
            'barang_id' => $ownFoundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => 'pending',
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti-own.jpg'],
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalHilang', 1);
        $response->assertViewHas('totalTemuan', 1);
        $response->assertViewHas('menungguVerifikasi', 1);
        $response->assertSee('Laporan Wilayah Sendiri');
        $response->assertSee('Barang Wilayah Sendiri');
        $response->assertDontSee('Laporan Wilayah Lain');
        $response->assertDontSee('Barang Wilayah Lain');
    }

    private function extractTableBody(string $content): string
    {
        $start = strpos($content, '<tbody>');
        $end = strpos($content, '</tbody>');

        if ($start === false || $end === false || $end <= $start) {
            return $content;
        }

        return substr($content, $start, $end - $start);
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Dashboard',
            'nama' => 'User Dashboard',
            'username' => 'user-dashboard',
            'email' => 'dashboard-user@example.com',
            'nomor_telepon' => '081111111112',
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Dashboard',
            'email' => 'dashboard-super@example.com',
            'username' => 'super-dashboard',
            'password' => Hash::make('password123'),
        ]);

        $region = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Dashboard Default',
            'lat' => -6.31,
            'lng' => 108.31,
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin Dashboard',
            'email' => 'dashboard-admin@example.com',
            'username' => 'admin-dashboard',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Dashboard No. 1',
            'status_verifikasi' => 'active',
        ]);
    }

    private function createScopedAdmin(Wilayah $region, string $username): Admin
    {
        $superAdmin = SuperAdmin::query()->firstOrCreate(
            ['email' => 'dashboard-scope-super@example.com'],
            [
                'nama' => 'Super Admin Scope Dashboard',
                'username' => 'dashboard-scope-super',
                'password' => Hash::make('password123'),
            ]
        );

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin ' . $username,
            'email' => $username . '@example.com',
            'username' => $username,
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Lohbener',
            'alamat_lengkap' => 'Jl. Scope Dashboard No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
