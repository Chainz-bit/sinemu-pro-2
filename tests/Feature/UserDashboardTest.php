<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Klaim;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_user_dashboard_shows_stats_and_combined_latest_activities(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $otherUser = $this->createUser(
            email: 'other-user@example.com',
            username: 'other-dashboard-user',
            phone: '081111111113'
        );
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Laptop Lenovo',
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => now()->subDays(3)->toDateString(),
            'keterangan' => 'Tertinggal di meja baca',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'updated_at' => now()->subHours(3),
        ]);

        Barang::query()->create([
            'admin_id' => $admin->id,
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

        LaporanBarangHilang::query()->create([
            'user_id' => $otherUser->id,
            'nama_barang' => 'Jam Tangan',
            'lokasi_hilang' => 'Lapangan',
            'tanggal_hilang' => now()->subDays(5)->toDateString(),
            'keterangan' => 'Milik user lain',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $response = $this->actingAs($user)->get(route('user.dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalLaporHilang', 2);
        $response->assertViewHas('totalPengajuanKlaim', 1);
        $response->assertViewHas('menungguVerifikasi', 1);

        $latestActivities = $response->viewData('latestActivities');

        $this->assertInstanceOf(LengthAwarePaginator::class, $latestActivities);
        $this->assertSame(5, $latestActivities->total());

        $items = collect($latestActivities->items());

        $this->assertSame(
            $items->pluck('activity_at')->sortDesc()->values()->all(),
            $items->pluck('activity_at')->values()->all()
        );
        $this->assertEqualsCanonicalizing(
            ['HP Samsung', 'HP Samsung', 'HP Samsung', 'Tas Biru', 'Laptop Lenovo'],
            $items->pluck('item_name')->all()
        );
        $this->assertEqualsCanonicalizing(
            ['claim', 'found_report', 'lost_report', 'found_report', 'lost_report'],
            $items->pluck('type')->all()
        );
        $this->assertEqualsCanonicalizing(
            ['menunggu_tinjauan', 'sedang_diproses', 'sedang_diproses', 'terverifikasi', 'terverifikasi'],
            $items->pluck('status')->all()
        );
    }

    public function test_user_dashboard_applies_search_and_status_filters(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
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
            'catatan' => 'Klaim disetujui admin',
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti-2.jpg'],
            'bukti_ciri_khusus' => 'Ada casing abu-abu',
            'bukti_lokasi_spesifik' => 'Meja belakang',
            'bukti_waktu_hilang' => '09:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('user.dashboard', [
            'search' => 'Tablet Xiaomi',
            'status' => 'sedang_diproses',
        ]));

        $response->assertOk();
        $response->assertViewHas('statusFilter', 'sedang_diproses');
        $response->assertViewHas('search', 'Tablet Xiaomi');

        $latestActivities = $response->viewData('latestActivities');

        $this->assertInstanceOf(LengthAwarePaginator::class, $latestActivities);
        $this->assertSame(3, $latestActivities->total());

        $items = collect($latestActivities->items());

        $this->assertCount(3, $items);
        $this->assertTrue($items->every(fn ($item) => $item->item_name === 'Tablet Xiaomi'));
        $this->assertTrue($items->every(fn ($item) => $item->status === 'sedang_diproses'));
        $this->assertEqualsCanonicalizing(
            ['claim', 'found_report', 'lost_report'],
            $items->pluck('type')->all()
        );
    }

    private function createUser(string $email = 'dashboard-user@example.com', string $username = 'user-dashboard', string $phone = '081111111112'): User
    {
        $user = User::query()->create([
            'name' => 'User Dashboard',
            'nama' => 'User Dashboard',
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
            'nama' => 'Super Admin Dashboard User',
            'email' => 'dashboard-user-super@example.com',
            'username' => 'super-user-dashboard',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Dashboard User',
            'email' => 'dashboard-user-admin@example.com',
            'username' => 'admin-user-dashboard',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Dashboard User No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
