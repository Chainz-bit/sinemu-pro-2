<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Wilayah;
use App\Rules\RegionHasActiveAdmin;
use App\Services\Admin\Matching\MatchingService;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegionalReportScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_lost_report_when_region_has_active_admin(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin(status: Admin::STATUS_ACTIVE);

        $this->actingAs($user)
            ->from(route('user.lost-reports.create'))
            ->post(route('user.lost-reports.store'), $this->validLostReportPayload($admin->region_id))
            ->assertRedirect(route('user.lost-reports.create'));

        $this->assertDatabaseHas('laporan_barang_hilangs', [
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laptop Wilayah',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
        ]);
    }

    public function test_user_cannot_create_lost_report_when_region_has_no_active_admin(): void
    {
        $user = $this->createUser();
        $region = $this->createRegion('Wilayah Tanpa Pengelola Laporan Hilang');

        foreach ([Admin::STATUS_PENDING, Admin::STATUS_REJECTED, Admin::STATUS_INACTIVE] as $status) {
            $this->createAdmin(region: $region, status: $status, username: 'admin-' . $status);
        }

        $softDeletedAdmin = $this->createAdmin(region: $region, status: Admin::STATUS_ACTIVE, username: 'admin-soft-deleted');
        $softDeletedAdmin->delete();

        $this->actingAs($user)
            ->from(route('user.lost-reports.create'))
            ->post(route('user.lost-reports.store'), $this->validLostReportPayload($region->id))
            ->assertRedirect(route('user.lost-reports.create'))
            ->assertSessionHasErrors(['region_id' => RegionHasActiveAdmin::MESSAGE]);

        $this->assertDatabaseMissing('laporan_barang_hilangs', [
            'user_id' => $user->id,
            'region_id' => $region->id,
            'nama_barang' => 'Laptop Wilayah',
        ]);
    }

    public function test_user_can_create_found_report_when_region_has_active_admin(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin(status: Admin::STATUS_ACTIVE);
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $this->actingAs($user)
            ->from(route('user.found-reports.create'))
            ->post(route('user.found-reports.store'), $this->validFoundReportPayload($admin->region_id, $kategori->id))
            ->assertRedirect(route('user.found-reports.create'));

        $this->assertDatabaseHas('barangs', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Dompet Wilayah',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
        ]);
    }

    public function test_user_cannot_create_found_report_when_region_has_no_active_admin(): void
    {
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $region = $this->createRegion('Wilayah Tanpa Pengelola Barang Temuan');

        foreach ([Admin::STATUS_PENDING, Admin::STATUS_REJECTED, Admin::STATUS_INACTIVE] as $status) {
            $this->createAdmin(region: $region, status: $status, username: 'found-admin-' . $status);
        }

        $softDeletedAdmin = $this->createAdmin(region: $region, status: Admin::STATUS_ACTIVE, username: 'found-admin-soft-deleted');
        $softDeletedAdmin->delete();

        $this->actingAs($user)
            ->from(route('user.found-reports.create'))
            ->post(route('user.found-reports.store'), $this->validFoundReportPayload($region->id, $kategori->id))
            ->assertRedirect(route('user.found-reports.create'))
            ->assertSessionHasErrors(['region_id' => RegionHasActiveAdmin::MESSAGE]);

        $this->assertDatabaseMissing('barangs', [
            'user_id' => $user->id,
            'region_id' => $region->id,
            'nama_barang' => 'Dompet Wilayah',
        ]);
    }

    public function test_matching_candidates_are_limited_to_same_region(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin(status: Admin::STATUS_ACTIVE);
        $otherRegion = $this->createRegion('Wilayah Kandidat Lain');
        $otherAdmin = $this->createAdmin(region: $otherRegion, status: Admin::STATUS_ACTIVE, username: 'admin-kandidat-lain');
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Kamera Canon',
            'kategori_barang' => 'Elektronik',
            'warna_barang' => 'Hitam',
            'lokasi_hilang' => 'Ruang rapat',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Kamera hitam hilang di ruang rapat',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ]);

        $sameRegionFoundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Kamera Canon',
            'warna_barang' => 'Hitam',
            'deskripsi' => 'Ditemukan kamera hitam di ruang rapat',
            'lokasi_ditemukan' => 'Ruang rapat',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ]);

        $otherRegionFoundItem = Barang::query()->create([
            'admin_id' => $otherAdmin->id,
            'region_id' => $otherRegion->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Kamera Canon',
            'warna_barang' => 'Hitam',
            'deskripsi' => 'Ditemukan kamera hitam di ruang rapat',
            'lokasi_ditemukan' => 'Ruang rapat',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ]);

        $candidates = $this->app
            ->make(MatchingService::class)
            ->findCandidatesForLostReport($lostReport);

        $candidateIds = $candidates->pluck('barang.id')->all();

        $this->assertContains($sameRegionFoundItem->id, $candidateIds);
        $this->assertNotContains($otherRegionFoundItem->id, $candidateIds);
    }

    private function validLostReportPayload(int $regionId): array
    {
        return [
            'nama_barang' => 'Laptop Wilayah',
            'region_id' => $regionId,
            'kategori_barang' => 'Elektronik',
            'lokasi_hilang' => 'Kantor wilayah',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Hilang saat kegiatan',
            'kontak_pelapor' => '081234567890',
        ];
    }

    private function validFoundReportPayload(int $regionId, int $kategoriId): array
    {
        return [
            'nama_barang' => 'Dompet Wilayah',
            'region_id' => $regionId,
            'kategori_id' => $kategoriId,
            'deskripsi' => 'Dompet ditemukan di kantor wilayah',
            'kontak_penemu' => '081234567890',
            'lokasi_ditemukan' => 'Kantor wilayah',
            'tanggal_ditemukan' => now()->toDateString(),
        ];
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Wilayah',
            'nama' => 'User Wilayah',
            'username' => 'user-wilayah-' . str()->random(6),
            'email' => str()->random(8) . '@example.com',
            'nomor_telepon' => '0812' . random_int(10000000, 99999999),
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(
        ?Wilayah $region = null,
        string $status = Admin::STATUS_ACTIVE,
        string $username = 'admin-wilayah'
    ): Admin {
        $superAdmin = SuperAdmin::query()->firstOrCreate(
            ['email' => 'regional-super@example.com'],
            [
                'nama' => 'Super Admin Wilayah',
                'username' => 'regional-super',
                'password' => Hash::make('password123'),
            ]
        );
        $region ??= $this->createRegion('Wilayah Admin ' . $username);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin Wilayah ' . $username,
            'email' => $username . '@example.com',
            'username' => $username,
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi Wilayah',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Wilayah No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === Admin::STATUS_ACTIVE ? now() : null,
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
