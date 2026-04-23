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
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Home',
            'email' => 'home-super@example.com',
            'username' => 'super-home',
            'password' => Hash::make('password123'),
        ]);

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
}
