<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminItemEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_found_item_edit_page(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $barang = $this->createFoundItem($admin, $user, $kategori);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items.edit', $barang))
            ->assertOk()
            ->assertSee('Edit Data Barang Temuan')
            ->assertSee($barang->nama_barang);
    }

    public function test_admin_can_update_found_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $barang = $this->createFoundItem($admin, $user, $kategori);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $barang), $this->validFoundItemPayload($kategori));

        $response->assertRedirect(route('admin.found-items.show', $barang));
        $response->assertSessionHas('status', 'Data barang temuan berhasil diperbarui.');

        $barang = $barang->fresh();

        $this->assertSame('Laptop ASUS', $barang?->nama_barang);
        $this->assertSame('Ruang Dosen', $barang?->lokasi_ditemukan);
        $this->assertSame('2026-04-20', $barang?->tanggal_ditemukan);
    }

    public function test_found_item_update_validates_required_fields(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $barang = $this->createFoundItem($admin, $user, $kategori);

        $response = $this->from(route('admin.found-items.edit', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $barang), [
                'kategori_id' => $kategori->id,
                'deskripsi' => 'Payload tanpa field wajib.',
            ]);

        $response->assertRedirect(route('admin.found-items.edit', $barang));
        $response->assertSessionHasErrors([
            'nama_barang',
            'lokasi_ditemukan',
            'tanggal_ditemukan',
        ]);

        $this->assertSame('Tas Abu-abu', $barang->fresh()?->nama_barang);
    }

    public function test_admin_can_open_lost_item_edit_page(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $laporanBarangHilang = $this->createLostItem($user);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items.edit', $laporanBarangHilang))
            ->assertOk()
            ->assertSee('Edit Data Barang Hilang')
            ->assertSee($laporanBarangHilang->nama_barang);
    }

    public function test_lost_item_edit_redirects_for_non_lapor_hilang_source(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();

        $laporanBarangHilang = $this->createLostItem($user, [
            'sumber_laporan' => 'claim',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items.edit', $laporanBarangHilang))
            ->assertRedirect(route('admin.lost-items'));
    }

    public function test_admin_can_update_lost_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();

        $laporanBarangHilang = $this->createLostItem($user);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update', $laporanBarangHilang), $this->validLostItemPayload());

        $response->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang));
        $response->assertSessionHas('status', 'Data barang hilang berhasil diperbarui.');

        $laporanBarangHilang = $laporanBarangHilang->fresh();

        $this->assertSame('Dompet Hitam', $laporanBarangHilang?->nama_barang);
        $this->assertSame('Gedung Serbaguna', $laporanBarangHilang?->lokasi_hilang);
        $this->assertSame('2026-04-18', $laporanBarangHilang?->tanggal_hilang);
    }

    public function test_lost_item_update_validates_required_fields(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();

        $laporanBarangHilang = $this->createLostItem($user);

        $response = $this->from(route('admin.lost-items.edit', $laporanBarangHilang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update', $laporanBarangHilang), [
                'kategori_barang' => 'Aksesoris',
            ]);

        $response->assertRedirect(route('admin.lost-items.edit', $laporanBarangHilang));
        $response->assertSessionHasErrors([
            'nama_barang',
            'lokasi_hilang',
            'tanggal_hilang',
            'keterangan',
        ]);

        $this->assertSame('Dompet Cokelat', $laporanBarangHilang->fresh()?->nama_barang);
    }

    /**
     * @return array<string, mixed>
     */
    private function validFoundItemPayload(Kategori $kategori): array
    {
        return [
            'nama_barang' => 'Laptop ASUS',
            'kategori_id' => $kategori->id,
            'warna_barang' => 'Hitam',
            'merek_barang' => 'ASUS',
            'nomor_seri' => 'SN-FOUND-002',
            'deskripsi' => 'Ditemukan di meja depan ruang dosen.',
            'ciri_khusus' => 'Ada stiker kampus di cover.',
            'nama_penemu' => 'Satpam Kampus',
            'kontak_penemu' => '081200000001',
            'lokasi_ditemukan' => 'Ruang Dosen',
            'detail_lokasi_ditemukan' => 'Dekat meja resepsionis.',
            'tanggal_ditemukan' => '2026-04-20',
            'waktu_ditemukan' => '09:30',
            'lokasi_pengambilan' => 'Pos Keamanan',
            'alamat_pengambilan' => 'Gedung A',
            'penanggung_jawab_pengambilan' => 'Petugas Jaga',
            'kontak_pengambilan' => '081200000002',
            'jam_layanan_pengambilan' => '08:00-16:00',
            'catatan_pengambilan' => 'Tunjukkan kartu identitas.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validLostItemPayload(): array
    {
        return [
            'nama_barang' => 'Dompet Hitam',
            'kategori_barang' => 'Aksesoris',
            'warna_barang' => 'Hitam',
            'merek_barang' => 'Eiger',
            'nomor_seri' => 'N/A',
            'lokasi_hilang' => 'Gedung Serbaguna',
            'detail_lokasi_hilang' => 'Area kursi baris depan.',
            'tanggal_hilang' => '2026-04-18',
            'waktu_hilang' => '13:15',
            'keterangan' => 'Dompet berisi kartu mahasiswa dan uang tunai.',
            'ciri_khusus' => 'Ada gantungan kecil warna merah.',
            'kontak_pelapor' => '081233344455',
            'bukti_kepemilikan' => 'Ada foto keluarga di slot transparan.',
        ];
    }

    private function createFoundItem(Admin $admin, User $user, Kategori $kategori): Barang
    {
        return Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Tas Abu-abu',
            'warna_barang' => 'Abu-abu',
            'merek_barang' => 'Eiger',
            'nomor_seri' => 'SN-FOUND-001',
            'deskripsi' => 'Ditemukan di lorong kampus.',
            'ciri_khusus' => 'Ada gantungan kunci.',
            'nama_penemu' => 'Petugas Kebersihan',
            'kontak_penemu' => '081100000001',
            'lokasi_ditemukan' => 'Koridor Kampus',
            'detail_lokasi_ditemukan' => 'Dekat tangga utama.',
            'tanggal_ditemukan' => '2026-04-17',
            'waktu_ditemukan' => '08:00',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createLostItem(User $user, array $overrides = []): LaporanBarangHilang
    {
        return LaporanBarangHilang::query()->create(array_merge([
            'user_id' => $user->id,
            'nama_barang' => 'Dompet Cokelat',
            'kategori_barang' => 'Aksesoris',
            'warna_barang' => 'Cokelat',
            'merek_barang' => 'Kulit Lokal',
            'nomor_seri' => 'N/A',
            'lokasi_hilang' => 'Kantin Kampus',
            'detail_lokasi_hilang' => 'Dekat meja kasir.',
            'tanggal_hilang' => '2026-04-16',
            'waktu_hilang' => '12:30',
            'keterangan' => 'Berisi kartu mahasiswa dan SIM.',
            'ciri_khusus' => 'Ada bekas lipatan di sudut kiri.',
            'kontak_pelapor' => '081122233344',
            'bukti_kepemilikan' => 'Foto keluarga di dalam dompet.',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ], $overrides));
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Edit Admin',
            'nama' => 'User Edit Admin',
            'username' => 'user-edit-admin',
            'email' => 'admin-item-user@example.com',
            'nomor_telepon' => '081111111113',
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Item',
            'email' => 'admin-item-super@example.com',
            'username' => 'super-item-admin',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Item',
            'email' => 'admin-item@example.com',
            'username' => 'admin-item',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Admin Item No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
