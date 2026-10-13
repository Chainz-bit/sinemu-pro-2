<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\Wilayah;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminInputItemRegionTest extends TestCase
{
    use RefreshDatabase;

    private const MISSING_REGION_MESSAGE = 'Pengelola harus memiliki wilayah aktif sebelum membuat data barang.';

    public function test_admin_with_region_can_create_lost_input_item_with_region_scope(): void
    {
        $admin = $this->createAdmin($this->createRegion('Wilayah Input Hilang'));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.input-items.store'), $this->lostPayload())
            ->assertRedirect()
            ->assertSessionHas('status', 'Laporan barang hilang berhasil ditambahkan.');

        $this->assertDatabaseHas('laporan_barang_hilangs', [
            'nama_barang' => 'Kamera Input Hilang',
            'region_id' => $admin->region_id,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ]);
    }

    public function test_lost_input_item_stores_verification_admin_and_timestamp(): void
    {
        $admin = $this->createAdmin($this->createRegion('Wilayah Verifikasi Hilang'));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.input-items.store'), $this->lostPayload())
            ->assertRedirect()
            ->assertSessionHas('status');

        $report = LaporanBarangHilang::query()->where('nama_barang', 'Kamera Input Hilang')->firstOrFail();

        $this->assertSame((int) $admin->id, (int) $report->verified_by_admin_id);
        $this->assertNotNull($report->verified_at);
    }

    public function test_admin_without_region_cannot_create_lost_input_item(): void
    {
        $admin = $this->createAdmin(null);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.input-items.store'), $this->lostPayload())
            ->assertRedirect()
            ->assertSessionHas('error', self::MISSING_REGION_MESSAGE);

        $this->assertDatabaseMissing('laporan_barang_hilangs', [
            'nama_barang' => 'Kamera Input Hilang',
        ]);
    }

    public function test_admin_without_region_cannot_create_found_input_item(): void
    {
        $admin = $this->createAdmin(null);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.input-items.store'), $this->foundPayload())
            ->assertRedirect()
            ->assertSessionHas('error', self::MISSING_REGION_MESSAGE);

        $this->assertDatabaseMissing('barangs', [
            'nama_barang' => 'Payung Input Temuan',
        ]);
    }

    public function test_approved_input_item_data_is_never_created_without_region_id(): void
    {
        $admin = $this->createAdmin($this->createRegion('Wilayah Data Approved'));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.input-items.store'), $this->lostPayload())
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.input-items.store'), $this->foundPayload())
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('laporan_barang_hilangs', [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'region_id' => null,
        ]);
        $this->assertDatabaseMissing('barangs', [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'region_id' => null,
        ]);

        $this->assertSame(1, LaporanBarangHilang::query()->where('region_id', $admin->region_id)->count());
        $this->assertSame(1, Barang::query()->where('region_id', $admin->region_id)->count());
    }

    /**
     * @return array<string,mixed>
     */
    private function lostPayload(): array
    {
        return [
            'jenis_laporan' => 'hilang',
            'nama_barang' => 'Kamera Input Hilang',
            'tanggal_waktu' => now()->subDay()->format('Y-m-d H:i:s'),
            'lokasi' => 'Aula Kecamatan',
            'nama_pelapor' => 'Pelapor Input Hilang',
            'deskripsi' => 'Kamera hilang setelah acara.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function foundPayload(): array
    {
        return [
            'jenis_laporan' => 'temuan',
            'nama_barang' => 'Payung Input Temuan',
            'tanggal_waktu' => now()->format('Y-m-d H:i:s'),
            'lokasi' => 'Loket Pelayanan',
            'nama_pelapor' => 'Penemu Input Temuan',
            'deskripsi' => 'Payung ditemukan di dekat loket.',
            'lokasi_pengambilan' => 'Kantor Kecamatan',
            'alamat_pengambilan' => 'Jl. Pelayanan No. 1',
            'penanggung_jawab_pengambilan' => 'Petugas Barang',
            'kontak_pengambilan' => '081234567890',
        ];
    }

    private function createAdmin(?Wilayah $region): Admin
    {
        $suffix = str()->random(8);
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Input '.$suffix,
            'email' => 'super-input-'.$suffix.'@example.com',
            'username' => 'super-input-'.$suffix,
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region?->id,
            'nama' => 'Admin Input '.$suffix,
            'email' => 'admin-input-'.$suffix.'@example.com',
            'username' => 'admin-input-'.$suffix,
            'password' => Hash::make('password123'),
            'instansi' => 'Kantor Input',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Input No. 1',
            'status_verifikasi' => Admin::STATUS_ACTIVE,
            'verified_at' => now(),
        ]);
    }

    private function createRegion(string $name): Wilayah
    {
        return Wilayah::query()->create([
            'nama_wilayah' => $name,
            'lat' => -6.326,
            'lng' => 108.32,
        ]);
    }
}
