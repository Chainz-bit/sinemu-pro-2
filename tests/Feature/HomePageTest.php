<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_landing_page_and_sees_guest_actions(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Kehilangan atau Menemukan', false);
        $response->assertSee('Masuk', false);
        $response->assertSee('data-bs-target="#loginPortalModal"', false);
        $response->assertDontSee(route('user.lost-reports.create'), false);
    }

    public function test_regular_user_can_open_landing_page_with_user_menu(): void
    {
        $user = User::factory()->create([
            'name' => 'Landing User',
            'email' => 'landing-user@example.com',
        ]);

        $response = $this->actingAs($user, 'web')->get(route('home'));

        $response->assertOk();
        $response->assertSee('Landing User', false);
        $response->assertSee(route('user.lost-reports.create'), false);
    }

    public function test_authenticated_admin_is_redirected_from_landing_to_admin_dashboard(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('home'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_super_admin_is_redirected_from_landing_to_super_dashboard(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin, 'super_admin')
            ->get(route('home'))
            ->assertRedirect(route('super.dashboard'));
    }

    public function test_admin_logout_redirects_to_landing_as_guest(): void
    {
        $admin = $this->createAdmin();
        $token = 'landing-admin-logout-token';

        $this->actingAs($admin, 'admin')
            ->withSession(['_token' => $token])
            ->post(route('admin.logout'), ['_token' => $token])
            ->assertRedirect(route('home'));

        $this->assertGuest('admin');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Masuk', false);
    }

    public function test_super_admin_logout_redirects_to_landing_as_guest(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = 'landing-super-logout-token';

        $this->actingAs($superAdmin, 'super_admin')
            ->withSession(['_token' => $token])
            ->post(route('super.logout'), ['_token' => $token])
            ->assertRedirect(route('home'));

        $this->assertGuest('super_admin');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Masuk', false);
    }

    public function test_landing_search_query_with_empty_fields_does_not_error(): void
    {
        $this->get('/?keyword=&category=&date=&region=')
            ->assertOk();
    }

    public function test_home_page_builds_public_lists_metadata_and_pickup_locations(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        Wilayah::query()->create([
            'nama_wilayah' => 'Kecamatan Sindang',
            'lat' => -6.322,
            'lng' => 108.324,
        ]);

        LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Laptop Lenovo',
            'kategori_barang' => 'Elektronik',
            'lokasi_hilang' => 'kec sindang',
            'tanggal_hilang' => now()->subDays(3)->toDateString(),
            'keterangan' => 'Tertinggal di perpustakaan',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'updated_at' => now()->subHours(2),
        ]);

        Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Tas Biru',
            'deskripsi' => 'Ditemukan di kursi ruang tunggu',
            'lokasi_ditemukan' => 'Kecamatan Sindang',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'updated_at' => now()->subHour(),
        ]);

        LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Dompet Coklat',
            'kategori_barang' => 'Aksesoris',
            'lokasi_hilang' => 'Jatibarang',
            'tanggal_hilang' => now()->subDays(5)->toDateString(),
            'keterangan' => 'Data yang tidak boleh tampil',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'tampil_di_home' => false,
        ]);

        LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Kartu Rahasia',
            'kategori_barang' => 'Dokumen',
            'lokasi_hilang' => 'Kecamatan Sindang',
            'tanggal_hilang' => now()->subDays(4)->toDateString(),
            'keterangan' => 'Approved tetapi belum dipublikasikan',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Jam Tangan Privat',
            'deskripsi' => 'Approved tetapi belum dipublikasikan',
            'lokasi_ditemukan' => 'Kecamatan Sindang',
            'tanggal_ditemukan' => now()->subDays(2)->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();

        $lostItems = collect($response->viewData('lostItems'));
        $foundItems = collect($response->viewData('foundItems'));
        $categories = $response->viewData('categories');
        $regions = $response->viewData('regions');
        $mapRegions = collect($response->viewData('mapRegions'));
        $pickupLocations = collect($response->viewData('pickupLocations'));

        $this->assertCount(1, $lostItems);
        $this->assertCount(1, $foundItems);
        $this->assertSame('Laptop Lenovo', $lostItems->first()['name']);
        $this->assertSame('Tas Biru', $foundItems->first()['name']);
        $this->assertSame(now()->subDays(3)->toDateString(), $lostItems->first()['date']);
        $this->assertSame(now()->subDay()->toDateString(), $foundItems->first()['date']);
        $this->assertContains('Elektronik', $categories);
        $this->assertContains('Kecamatan Sindang', $regions);
        $this->assertSame(1, $pickupLocations->count());
        $this->assertSame('Kampus SINEMU', $pickupLocations->first()['name']);
        $this->assertTrue($mapRegions->contains(function (array $region) {
            return $region['name'] === 'Kecamatan Sindang' && $region['active_points'] >= 2;
        }));
    }

    public function test_found_detail_marks_available_item_as_claimable(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $barang = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Tablet Samsung',
            'deskripsi' => 'Ditemukan di ruang kelas',
            'lokasi_ditemukan' => 'Kecamatan Sindang',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ]);

        $response = $this->get(route('home.found-detail', $barang));

        $response->assertOk();

        $detail = $response->viewData('detail');

        $this->assertTrue($detail->is_claimable);
        $this->assertSame('Ajukan Klaim', $detail->claim_action_label);
        $this->assertSame(route('user.claims.create', ['barang_id' => $barang->id]), $detail->claim_action_url);
        $this->assertSame('Tersedia untuk Diklaim', $detail->status_label);
    }

    public function test_found_detail_marks_in_progress_item_as_not_claimable(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser(email: 'detail-user@example.com', username: 'detail-dashboard-user', phone: '081111111119');
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $barang = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'iPhone 13',
            'deskripsi' => 'Sedang diverifikasi',
            'lokasi_ditemukan' => 'Kecamatan Sindang',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => 'dalam_proses_klaim',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => true,
        ]);

        $response = $this->get(route('home.found-detail', $barang));

        $response->assertOk();

        $detail = $response->viewData('detail');

        $this->assertFalse($detail->is_claimable);
        $this->assertSame('Lihat Status Klaim', $detail->claim_action_label);
        $this->assertSame(route('user.claim-history'), $detail->claim_action_url);
        $this->assertSame('Sedang Diproses Klaim', $detail->status_label);
    }

    public function test_unpublished_public_detail_returns_not_found(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $laporan = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Laptop Kantor',
            'kategori_barang' => 'Elektronik',
            'lokasi_hilang' => 'Kecamatan Sindang',
            'tanggal_hilang' => now()->subDays(3)->toDateString(),
            'keterangan' => 'Belum boleh tampil publik',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $barang = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Tablet Privat',
            'deskripsi' => 'Belum boleh tampil publik',
            'lokasi_ditemukan' => 'Kecamatan Sindang',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $this->get(route('home.lost-detail', $laporan))->assertNotFound();
        $this->get(route('home.found-detail', $barang))->assertNotFound();
    }

    private function createUser(
        string $email = 'home-user@example.com',
        string $username = 'home-user',
        string $phone = '081111111118'
    ): User {
        $user = User::query()->create([
            'name' => 'Home User',
            'nama' => 'Home User',
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
        $superAdmin = $this->createSuperAdmin();

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Home',
            'email' => 'home-admin@example.com',
            'username' => 'admin-home',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Home No. 1',
            'status_verifikasi' => 'active',
            'lat' => -6.322,
            'lng' => 108.324,
        ]);
    }

    private function createSuperAdmin(
        string $email = 'home-super@example.com',
        string $username = 'super-home'
    ): SuperAdmin {
        return SuperAdmin::query()->create([
            'nama' => 'Super Admin Home',
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('password123'),
        ]);
    }
}
