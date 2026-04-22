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

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
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
}
