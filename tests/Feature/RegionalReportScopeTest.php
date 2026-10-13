<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            ->post(route('user.lost-reports.store'), $this->validLostReportPayload($admin->region_id) + [
                'status_laporan' => WorkflowStatus::REPORT_APPROVED,
                'status_barang' => WorkflowStatus::FOUND_CLAIMED,
                'admin_id' => $admin->id,
                'verified_by_admin_id' => $admin->id,
                'tampil_di_home' => true,
            ])
            ->assertRedirect(route('user.lost-reports.create'));

        $this->assertDatabaseHas('laporan_barang_hilangs', [
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laptop Wilayah',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
            'verified_by_admin_id' => null,
            'verified_at' => null,
        ]);
    }

    public function test_user_cannot_create_lost_report_when_region_has_no_active_admin(): void
    {
        $user = $this->createUser();
        $region = $this->createRegion('Wilayah Tanpa Pengelola Laporan Hilang');

        foreach ([Admin::STATUS_PENDING, Admin::STATUS_REJECTED, Admin::STATUS_INACTIVE] as $status) {
            $this->createAdmin(region: $region, status: $status, username: 'admin-'.$status);
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

    public function test_lost_report_dropdown_lists_only_regions_with_active_admins(): void
    {
        $user = $this->createUser();
        $activeRegion = $this->createRegion('Wilayah Hilang Aktif');
        $noAdminRegion = $this->createRegion('Wilayah Hilang Kosong');
        $pendingRegion = $this->createRegion('Wilayah Hilang Pending');
        $rejectedRegion = $this->createRegion('Wilayah Hilang Ditolak');
        $inactiveRegion = $this->createRegion('Wilayah Hilang Nonaktif');
        $softDeletedRegion = $this->createRegion('Wilayah Hilang Terhapus');

        $this->createAdmin(region: $activeRegion, status: Admin::STATUS_ACTIVE, username: 'lost-dropdown-active');
        $this->createAdmin(region: $pendingRegion, status: Admin::STATUS_PENDING, username: 'lost-dropdown-pending');
        $this->createAdmin(region: $rejectedRegion, status: Admin::STATUS_REJECTED, username: 'lost-dropdown-rejected');
        $this->createAdmin(region: $inactiveRegion, status: Admin::STATUS_INACTIVE, username: 'lost-dropdown-inactive');
        $softDeletedAdmin = $this->createAdmin(region: $softDeletedRegion, status: Admin::STATUS_ACTIVE, username: 'lost-dropdown-soft');
        $softDeletedAdmin->delete();
        $this->createAdminWithoutRegion('lost-dropdown-without-region');

        $response = $this->actingAs($user)
            ->get(route('user.lost-reports.create'))
            ->assertOk();

        $optionIds = $response->viewData('wilayahOptions')->pluck('id')->all();

        $this->assertSame([$activeRegion->id], $optionIds);
        $this->assertNotContains($noAdminRegion->id, $optionIds);
        $this->assertNotContains($pendingRegion->id, $optionIds);
        $this->assertNotContains($rejectedRegion->id, $optionIds);
        $this->assertNotContains($inactiveRegion->id, $optionIds);
        $this->assertNotContains($softDeletedRegion->id, $optionIds);
    }

    public function test_lost_report_dropdown_shows_empty_state_when_no_region_is_available(): void
    {
        $user = $this->createUser();
        $this->createRegion('Wilayah Hilang Belum Tersedia');

        $this->actingAs($user)
            ->get(route('user.lost-reports.create'))
            ->assertOk()
            ->assertSee('Belum ada wilayah yang tersedia')
            ->assertSee('Saat ini belum ada wilayah dengan pengelola aktif. Silakan hubungi Support SiNemu.');
    }

    public function test_lost_report_rejects_missing_and_fake_region_id(): void
    {
        $user = $this->createUser();

        $missingRegionPayload = $this->validLostReportPayload(1);
        unset($missingRegionPayload['region_id']);

        $this->actingAs($user)
            ->from(route('user.lost-reports.create'))
            ->post(route('user.lost-reports.store'), $missingRegionPayload)
            ->assertRedirect(route('user.lost-reports.create'))
            ->assertSessionHasErrors('region_id');

        $this->actingAs($user)
            ->from(route('user.lost-reports.create'))
            ->post(route('user.lost-reports.store'), $this->validLostReportPayload(999999))
            ->assertRedirect(route('user.lost-reports.create'))
            ->assertSessionHasErrors('region_id');

        $this->assertDatabaseCount('laporan_barang_hilangs', 0);
    }

    public function test_lost_report_notification_is_sent_only_to_active_admins_in_same_region(): void
    {
        $user = $this->createUser();
        $regionA = $this->createRegion('Wilayah Notifikasi A');
        $regionB = $this->createRegion('Wilayah Notifikasi B');

        $activeRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_ACTIVE, username: 'notif-active-a');
        $secondActiveRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_ACTIVE, username: 'notif-active-a2');
        $activeRegionB = $this->createAdmin(region: $regionB, status: Admin::STATUS_ACTIVE, username: 'notif-active-b');
        $pendingRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_PENDING, username: 'notif-pending-a');
        $rejectedRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_REJECTED, username: 'notif-rejected-a');
        $inactiveRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_INACTIVE, username: 'notif-inactive-a');
        $softDeletedRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_ACTIVE, username: 'notif-soft-a');
        $softDeletedRegionA->delete();
        $adminWithoutRegion = $this->createAdminWithoutRegion('notif-without-region');

        LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $regionA->id,
            'nama_barang' => 'Laptop Notifikasi Wilayah',
            'kategori_barang' => 'Elektronik',
            'lokasi_hilang' => 'Kantor wilayah A',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Laporan untuk menguji notifikasi wilayah',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
        ]);

        foreach ([$activeRegionA, $secondActiveRegionA] as $admin) {
            $this->assertDatabaseHas('admin_notifications', [
                'admin_id' => $admin->id,
                'type' => 'laporan_hilang_baru',
                'title' => 'Laporan baru',
            ]);
        }

        foreach ([$activeRegionB, $pendingRegionA, $rejectedRegionA, $inactiveRegionA, $softDeletedRegionA, $adminWithoutRegion] as $admin) {
            $this->assertDatabaseMissing('admin_notifications', [
                'admin_id' => $admin->id,
                'type' => 'laporan_hilang_baru',
            ]);
        }

        $this->assertSame(2, AdminNotification::query()->where('type', 'laporan_hilang_baru')->count());
    }

    public function test_lost_report_without_region_does_not_create_global_admin_notification(): void
    {
        $user = $this->createUser();
        $region = $this->createRegion('Wilayah Tidak Boleh Broadcast');

        $this->createAdmin(region: $region, status: Admin::STATUS_ACTIVE, username: 'no-global-active');
        $this->createAdmin(region: $region, status: Admin::STATUS_PENDING, username: 'no-global-pending');
        $this->createAdminWithoutRegion('no-global-without-region');

        LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => null,
            'nama_barang' => 'Laptop Tanpa Wilayah',
            'kategori_barang' => 'Elektronik',
            'lokasi_hilang' => 'Lokasi belum diketahui',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Laporan tanpa wilayah tidak boleh broadcast',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
        ]);

        $this->assertDatabaseCount('admin_notifications', 0);
    }

    public function test_failed_lost_report_region_validation_does_not_store_uploaded_photo(): void
    {
        Storage::fake('public');

        $user = $this->createUser();
        $region = $this->createRegion('Wilayah Upload Hilang Tanpa Admin');

        $this->actingAs($user)
            ->from(route('user.lost-reports.create'))
            ->post(route('user.lost-reports.store'), $this->validLostReportPayload($region->id) + [
                'foto_barang' => $this->fakePng('hilang-validasi.png'),
            ])
            ->assertRedirect(route('user.lost-reports.create'))
            ->assertSessionHasErrors(['region_id' => RegionHasActiveAdmin::MESSAGE]);

        $this->assertDatabaseCount('laporan_barang_hilangs', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_user_can_create_found_report_when_region_has_active_admin(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin(status: Admin::STATUS_ACTIVE);
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $this->actingAs($user)
            ->from(route('user.found-reports.create'))
            ->post(route('user.found-reports.store'), $this->validFoundReportPayload($admin->region_id, $kategori->id) + [
                'status_laporan' => WorkflowStatus::REPORT_APPROVED,
                'status_barang' => WorkflowStatus::FOUND_CLAIMED,
                'admin_id' => 999999,
                'verified_by_admin_id' => 999999,
                'tampil_di_home' => true,
            ])
            ->assertRedirect(route('user.found-reports.create'));

        $this->assertDatabaseHas('barangs', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Dompet Wilayah',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
            'verified_by_admin_id' => null,
            'verified_at' => null,
        ]);
    }

    public function test_found_report_dropdown_lists_only_regions_with_active_admins(): void
    {
        $user = $this->createUser();
        $activeRegion = $this->createRegion('Wilayah Temuan Aktif');
        $noAdminRegion = $this->createRegion('Wilayah Temuan Kosong');
        $pendingRegion = $this->createRegion('Wilayah Temuan Pending');
        $rejectedRegion = $this->createRegion('Wilayah Temuan Ditolak');
        $inactiveRegion = $this->createRegion('Wilayah Temuan Nonaktif');
        $softDeletedRegion = $this->createRegion('Wilayah Temuan Terhapus');

        $this->createAdmin(region: $activeRegion, status: Admin::STATUS_ACTIVE, username: 'found-dropdown-active');
        $this->createAdmin(region: $pendingRegion, status: Admin::STATUS_PENDING, username: 'found-dropdown-pending');
        $this->createAdmin(region: $rejectedRegion, status: Admin::STATUS_REJECTED, username: 'found-dropdown-rejected');
        $this->createAdmin(region: $inactiveRegion, status: Admin::STATUS_INACTIVE, username: 'found-dropdown-inactive');
        $softDeletedAdmin = $this->createAdmin(region: $softDeletedRegion, status: Admin::STATUS_ACTIVE, username: 'found-dropdown-soft');
        $softDeletedAdmin->delete();
        $this->createAdminWithoutRegion('found-dropdown-without-region');

        $response = $this->actingAs($user)
            ->get(route('user.found-reports.create'))
            ->assertOk();

        $optionIds = $response->viewData('wilayahOptions')->pluck('id')->all();

        $this->assertSame([$activeRegion->id], $optionIds);
        $this->assertNotContains($noAdminRegion->id, $optionIds);
        $this->assertNotContains($pendingRegion->id, $optionIds);
        $this->assertNotContains($rejectedRegion->id, $optionIds);
        $this->assertNotContains($inactiveRegion->id, $optionIds);
        $this->assertNotContains($softDeletedRegion->id, $optionIds);
    }

    public function test_found_report_dropdown_shows_empty_state_when_no_region_is_available(): void
    {
        $user = $this->createUser();
        $this->createRegion('Wilayah Temuan Belum Tersedia');

        $this->actingAs($user)
            ->get(route('user.found-reports.create'))
            ->assertOk()
            ->assertSee('Belum ada wilayah yang tersedia')
            ->assertSee('Saat ini belum ada wilayah dengan pengelola aktif. Silakan hubungi Support SiNemu.');
    }

    public function test_user_cannot_create_found_report_when_region_has_no_active_admin(): void
    {
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $region = $this->createRegion('Wilayah Tanpa Pengelola Barang Temuan');

        foreach ([Admin::STATUS_PENDING, Admin::STATUS_REJECTED, Admin::STATUS_INACTIVE] as $status) {
            $this->createAdmin(region: $region, status: $status, username: 'found-admin-'.$status);
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

    public function test_found_report_rejects_missing_and_fake_region_id(): void
    {
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $missingRegionPayload = $this->validFoundReportPayload(1, $kategori->id);
        unset($missingRegionPayload['region_id']);

        $this->actingAs($user)
            ->from(route('user.found-reports.create'))
            ->post(route('user.found-reports.store'), $missingRegionPayload)
            ->assertRedirect(route('user.found-reports.create'))
            ->assertSessionHasErrors('region_id');

        $this->actingAs($user)
            ->from(route('user.found-reports.create'))
            ->post(route('user.found-reports.store'), $this->validFoundReportPayload(999999, $kategori->id))
            ->assertRedirect(route('user.found-reports.create'))
            ->assertSessionHasErrors('region_id');

        $this->assertDatabaseCount('barangs', 0);
    }

    public function test_found_report_notification_is_sent_to_active_admins_in_same_region(): void
    {
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $regionA = $this->createRegion('Wilayah Temuan Notifikasi A');
        $regionB = $this->createRegion('Wilayah Temuan Notifikasi B');

        $activeRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_ACTIVE, username: 'found-notif-active-a');
        $secondActiveRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_ACTIVE, username: 'found-notif-active-a2');
        $activeRegionB = $this->createAdmin(region: $regionB, status: Admin::STATUS_ACTIVE, username: 'found-notif-active-b');
        $pendingRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_PENDING, username: 'found-notif-pending-a');
        $rejectedRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_REJECTED, username: 'found-notif-rejected-a');
        $inactiveRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_INACTIVE, username: 'found-notif-inactive-a');
        $softDeletedRegionA = $this->createAdmin(region: $regionA, status: Admin::STATUS_ACTIVE, username: 'found-notif-soft-a');
        $softDeletedRegionA->delete();
        $adminWithoutRegion = $this->createAdminWithoutRegion('found-notif-without-region');

        $this->actingAs($user)
            ->from(route('user.found-reports.create'))
            ->post(route('user.found-reports.store'), $this->validFoundReportPayload($regionA->id, $kategori->id))
            ->assertRedirect(route('user.found-reports.create'));

        foreach ([$activeRegionA, $secondActiveRegionA] as $admin) {
            $this->assertDatabaseHas('admin_notifications', [
                'admin_id' => $admin->id,
                'type' => 'barang_temuan_baru',
                'title' => 'Barang temuan baru',
            ]);
        }

        foreach ([$activeRegionB, $pendingRegionA, $rejectedRegionA, $inactiveRegionA, $softDeletedRegionA, $adminWithoutRegion] as $admin) {
            $this->assertDatabaseMissing('admin_notifications', [
                'admin_id' => $admin->id,
                'type' => 'barang_temuan_baru',
            ]);
        }

        $this->assertSame(2, AdminNotification::query()->where('type', 'barang_temuan_baru')->count());
    }

    public function test_failed_found_report_region_validation_does_not_store_uploaded_photo(): void
    {
        Storage::fake('public');

        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $region = $this->createRegion('Wilayah Upload Temuan Tanpa Admin');

        $this->actingAs($user)
            ->from(route('user.found-reports.create'))
            ->post(route('user.found-reports.store'), $this->validFoundReportPayload($region->id, $kategori->id) + [
                'foto_barang' => $this->fakePng('temuan-validasi.png'),
            ])
            ->assertRedirect(route('user.found-reports.create'))
            ->assertSessionHasErrors(['region_id' => RegionHasActiveAdmin::MESSAGE]);

        $this->assertDatabaseCount('barangs', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles());
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
            'username' => 'user-wilayah-'.str()->random(6),
            'email' => str()->random(8).'@example.com',
            'nomor_telepon' => '0812'.random_int(10000000, 99999999),
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
        $region ??= $this->createRegion('Wilayah Admin '.$username);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin Wilayah '.$username,
            'email' => $username.'@example.com',
            'username' => $username,
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi Wilayah',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Wilayah No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === Admin::STATUS_ACTIVE ? now() : null,
        ]);
    }

    private function createAdminWithoutRegion(string $username): Admin
    {
        $superAdmin = SuperAdmin::query()->firstOrCreate(
            ['email' => 'regional-super@example.com'],
            [
                'nama' => 'Super Admin Wilayah',
                'username' => 'regional-super',
                'password' => Hash::make('password123'),
            ]
        );

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => null,
            'nama' => 'Admin Tanpa Wilayah '.$username,
            'email' => $username.'@example.com',
            'username' => $username,
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi Tanpa Wilayah',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Tanpa Wilayah No. 1',
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

    private function fakePng(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lz+X6wAAAABJRU5ErkJggg==');
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
