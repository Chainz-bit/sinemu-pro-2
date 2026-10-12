<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_directory_shows_scoped_admins_for_all_statuses_by_default(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $otherSuperAdmin = $this->createSuperAdmin(
            email: 'other-directory-super@example.com',
            username: 'other-directory-super'
        );

        $pendingAdmin = $this->createAdmin($superAdmin, 'Admin Pending Direktori', 'pending');
        $activeAdmin = $this->createAdmin($superAdmin, 'Admin Aktif Direktori', 'active');
        $globalAdmin = $this->createAdmin(null, 'Admin Global Direktori', 'rejected');
        $this->createAdmin($otherSuperAdmin, 'Admin Super Lain Direktori', 'active');

        $response = $this->actingAs($superAdmin, 'super_admin')->get(route('super.admins.index'));

        $response->assertOk();
        $response->assertViewHas('search', '');
        $response->assertViewHas('statusFilter', 'semua');

        $admins = $response->viewData('admins');

        $this->assertInstanceOf(LengthAwarePaginator::class, $admins);
        $this->assertSame(3, $admins->total());
        $this->assertEqualsCanonicalizing(
            [$pendingAdmin->id, $activeAdmin->id, $globalAdmin->id],
            collect($admins->items())->pluck('id')->all()
        );
        $this->assertFalse(collect($admins->items())->pluck('nama')->contains('Admin Super Lain Direktori'));
    }

    public function test_admin_directory_applies_search_and_status_filters(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $targetAdmin = $this->createAdmin($superAdmin, 'Admin Sindang Aktif', 'active');
        $this->createAdmin($superAdmin, 'Admin Lohbener Aktif', 'active');
        $this->createAdmin($superAdmin, 'Admin Sindang Pending', 'pending');
        $inactiveAdmin = $this->createAdmin($superAdmin, 'Admin Sindang Nonaktif', 'inactive');

        $response = $this->actingAs($superAdmin, 'super_admin')->get(route('super.admins.index', [
            'search' => 'Sindang',
            'status' => 'active',
        ]));

        $response->assertOk();
        $response->assertViewHas('search', 'Sindang');
        $response->assertViewHas('statusFilter', 'active');

        $admins = $response->viewData('admins');

        $this->assertInstanceOf(LengthAwarePaginator::class, $admins);
        $this->assertSame(1, $admins->total());
        $this->assertSame($targetAdmin->id, collect($admins->items())->first()->id);

        $inactiveResponse = $this->actingAs($superAdmin, 'super_admin')->get(route('super.admins.index', [
            'search' => 'Sindang',
            'status' => 'inactive',
        ]));

        $inactiveAdmins = $inactiveResponse->viewData('admins');

        $inactiveResponse->assertOk();
        $this->assertSame(1, $inactiveAdmins->total());
        $this->assertSame($inactiveAdmin->id, collect($inactiveAdmins->items())->first()->id);
    }

    public function test_super_admin_can_create_show_edit_and_delete_manager_account(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $createResponse = $this->actingAs($superAdmin, 'super_admin')
            ->post(route('super.admins.store'), [
                'nama' => 'Angga Pengelola',
                'username' => 'angga-pengelola',
                'email' => 'angga-pengelola@example.com',
                'nomor_telepon' => '081234567890',
                'instansi' => 'Kantor Kecamatan Indramayu',
                'kecamatan' => 'Indramayu',
                'alamat_lengkap' => 'Jl. Merdeka No. 1',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'status_verifikasi' => 'active',
            ]);

        $admin = Admin::query()->where('username', 'angga-pengelola')->firstOrFail();

        $createResponse->assertRedirect(route('super.admins.show', $admin));
        $this->assertSame($superAdmin->id, $admin->super_admin_id);
        $this->assertSame('Indramayu', $admin->kecamatan);
        $this->assertNull($admin->pickup_address);
        $this->assertNull($admin->pickup_lat);
        $this->assertNull($admin->pickup_lng);
        $this->assertTrue(Hash::check('password123', (string) $admin->password));
        $this->assertNotSame('password123', $admin->password);

        $this->actingAs($superAdmin, 'super_admin')
            ->get(route('super.admins.show', $admin))
            ->assertOk()
            ->assertSee('Angga Pengelola')
            ->assertSee('Hapus Akun Pengelola?');

        $oldPassword = $admin->password;

        $this->actingAs($superAdmin, 'super_admin')
            ->put(route('super.admins.update', $admin), [
                'nama' => 'Angga Pengelola Update',
                'username' => 'angga-update',
                'email' => 'angga-update@example.com',
                'nomor_telepon' => '081234567891',
                'instansi' => 'Kantor Kecamatan Sindang',
                'kecamatan' => 'Sindang',
                'alamat_lengkap' => 'Jl. Sindang No. 2',
                'password' => '',
                'password_confirmation' => '',
                'status_verifikasi' => 'inactive',
            ])
            ->assertRedirect(route('super.admins.show', $admin));

        $admin->refresh();
        $this->assertSame('Angga Pengelola Update', $admin->nama);
        $this->assertSame('Sindang', $admin->kecamatan);
        $this->assertSame('inactive', $admin->status_verifikasi);
        $this->assertSame($oldPassword, $admin->password);

        $this->actingAs($superAdmin, 'super_admin')
            ->put(route('super.admins.update', $admin), [
                'nama' => 'Angga Pengelola Update',
                'username' => 'angga-update',
                'email' => 'angga-update@example.com',
                'nomor_telepon' => '081234567891',
                'instansi' => 'Kantor Kecamatan Sindang',
                'kecamatan' => 'Sindang',
                'alamat_lengkap' => 'Jl. Sindang No. 2',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
                'status_verifikasi' => 'active',
            ])
            ->assertRedirect(route('super.admins.show', $admin));

        $this->assertTrue(Hash::check('new-password123', (string) $admin->fresh()?->password));

        $this->actingAs($superAdmin, 'super_admin')
            ->delete(route('super.admins.destroy', $admin))
            ->assertRedirect(route('super.admins.index'));

        $this->assertSoftDeleted('admins', ['id' => $admin->id]);

        $this->actingAs($superAdmin, 'super_admin')
            ->get(route('super.admins.index', ['search' => 'Angga Pengelola Update']))
            ->assertOk()
            ->assertSee('Belum ada pengelola barang yang cocok dengan filter.')
            ->assertDontSee('angga-update@example.com');
    }

    public function test_super_admin_can_create_manager_with_valid_pickup_location(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin, 'super_admin')
            ->post(route('super.admins.store'), $this->validManagerPayload([
                'nama' => 'Admin Pickup Create',
                'username' => 'admin-pickup-create',
                'email' => 'admin-pickup-create@example.com',
                'pickup_address' => 'Kantor Kecamatan Lohbener, dekat ruang pelayanan',
                'pickup_lat' => '-6.365800',
                'pickup_lng' => '108.247100',
                'status_verifikasi' => Admin::STATUS_ACTIVE,
            ]))
            ->assertRedirect();

        $admin = Admin::query()->where('username', 'admin-pickup-create')->firstOrFail();

        $this->assertSame('Kantor Kecamatan Lohbener, dekat ruang pelayanan', $admin->pickup_address);
        $this->assertSame(-6.3658, $admin->pickup_lat);
        $this->assertSame(108.2471, $admin->pickup_lng);
    }

    public function test_super_admin_can_update_and_clear_manager_pickup_location(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $admin = $this->createAdmin($superAdmin, 'Admin Pickup Update', Admin::STATUS_ACTIVE);

        $this->actingAs($superAdmin, 'super_admin')
            ->put(route('super.admins.update', $admin), $this->validManagerPayload([
                'nama' => $admin->nama,
                'username' => $admin->username,
                'email' => $admin->email,
                'pickup_address' => 'Ruang pelayanan utama',
                'pickup_lat' => '-6.326400',
                'pickup_lng' => '108.322700',
                'password' => '',
                'password_confirmation' => '',
                'status_verifikasi' => Admin::STATUS_ACTIVE,
            ]))
            ->assertRedirect(route('super.admins.show', $admin));

        $admin->refresh();
        $this->assertSame('Ruang pelayanan utama', $admin->pickup_address);
        $this->assertSame(-6.3264, $admin->pickup_lat);
        $this->assertSame(108.3227, $admin->pickup_lng);

        $this->actingAs($superAdmin, 'super_admin')
            ->put(route('super.admins.update', $admin), $this->validManagerPayload([
                'nama' => $admin->nama,
                'username' => $admin->username,
                'email' => $admin->email,
                'pickup_address' => '',
                'pickup_lat' => '',
                'pickup_lng' => '',
                'password' => '',
                'password_confirmation' => '',
                'status_verifikasi' => Admin::STATUS_ACTIVE,
            ]))
            ->assertRedirect(route('super.admins.show', $admin));

        $admin->refresh();
        $this->assertNull($admin->pickup_address);
        $this->assertNull($admin->pickup_lat);
        $this->assertNull($admin->pickup_lng);
    }

    public function test_super_admin_pickup_coordinate_validation_requires_pair_and_valid_range(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $cases = [
            ['pickup_lat' => '-6.326400', 'pickup_lng' => '', 'error' => 'pickup_lng'],
            ['pickup_lat' => '', 'pickup_lng' => '108.322700', 'error' => 'pickup_lat'],
            ['pickup_lat' => '-91', 'pickup_lng' => '108.322700', 'error' => 'pickup_lat'],
            ['pickup_lat' => '-6.326400', 'pickup_lng' => '181', 'error' => 'pickup_lng'],
        ];

        foreach ($cases as $index => $case) {
            $this->actingAs($superAdmin, 'super_admin')
                ->from(route('super.admins.create'))
                ->post(route('super.admins.store'), $this->validManagerPayload([
                    'nama' => 'Admin Pickup Invalid '.$index,
                    'username' => 'admin-pickup-invalid-'.$index,
                    'email' => 'admin-pickup-invalid-'.$index.'@example.com',
                    'pickup_lat' => $case['pickup_lat'],
                    'pickup_lng' => $case['pickup_lng'],
                ]))
                ->assertRedirect(route('super.admins.create'))
                ->assertSessionHasErrors($case['error']);
        }
    }

    public function test_manager_account_create_rejects_role_injection_and_strictly_validates_phone(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $invalidPhoneResponse = $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admins.create'))
            ->post(route('super.admins.store'), [
                'nama' => 'Admin Phone Invalid',
                'username' => 'admin-phone-invalid',
                'email' => 'admin-phone-invalid@example.com',
                'nomor_telepon' => '08 1234-5678',
                'instansi' => 'Kantor Kecamatan Indramayu',
                'kecamatan' => 'Indramayu',
                'alamat_lengkap' => 'Jl. Validasi No. 1',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'status_verifikasi' => 'pending',
                'role' => 'super_admin',
                'is_super_admin' => true,
            ]);

        $invalidPhoneResponse->assertRedirect(route('super.admins.create'));
        $invalidPhoneResponse->assertSessionHasErrors('nomor_telepon');
        $this->assertDatabaseMissing('admins', ['username' => 'admin-phone-invalid']);

        $validPhoneResponse = $this->actingAs($superAdmin, 'super_admin')
            ->post(route('super.admins.store'), [
                'nama' => 'Admin Phone Valid',
                'username' => 'admin-phone-valid',
                'email' => 'admin-phone-valid@example.com',
                'nomor_telepon' => '+6281234567890',
                'instansi' => 'Kantor Kecamatan Indramayu',
                'kecamatan' => 'Indramayu',
                'alamat_lengkap' => 'Jl. Validasi No. 2',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'status_verifikasi' => 'pending',
                'role' => 'super_admin',
                'is_super_admin' => true,
            ]);

        $admin = Admin::query()->where('username', 'admin-phone-valid')->firstOrFail();

        $validPhoneResponse->assertRedirect(route('super.admins.show', $admin));
        $this->assertSame('+6281234567890', $admin->nomor_telepon);
        $this->assertArrayNotHasKey('role', $admin->getAttributes());
        $this->assertArrayNotHasKey('is_super_admin', $admin->getAttributes());
    }

    public function test_super_admin_cannot_delete_manager_account_with_matching_history(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $targetAdmin = $this->createAdmin($superAdmin, 'Admin Punya Pencocokan', 'active');
        $itemOwnerAdmin = $this->createAdmin($superAdmin, 'Admin Pemilik Barang', 'active');
        $user = User::factory()->create();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Dompet Kulit',
            'lokasi_hilang' => 'Alun-alun Indramayu',
            'tanggal_hilang' => now()->toDateString(),
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $itemOwnerAdmin->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Dompet Kulit',
            'deskripsi' => 'Dompet warna cokelat',
            'lokasi_ditemukan' => 'Alun-alun Indramayu',
            'tanggal_ditemukan' => now()->toDateString(),
        ]);

        Pencocokan::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'admin_id' => $targetAdmin->id,
            'status_pencocokan' => 'confirmed',
            'matched_at' => now(),
        ]);

        $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admins.index'))
            ->delete(route('super.admins.destroy', $targetAdmin))
            ->assertRedirect(route('super.admins.index'))
            ->assertSessionHas('error', 'Akun pengelola barang tidak dapat dihapus karena masih memiliki data laporan, klaim, atau pencocokan terkait.');

        $this->assertDatabaseHas('admins', ['id' => $targetAdmin->id]);
        $this->assertDatabaseHas('pencocokans', ['admin_id' => $targetAdmin->id]);
    }

    public function test_super_admin_cannot_create_manager_with_invalid_kecamatan(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admins.create'))
            ->post(route('super.admins.store'), [
                'nama' => 'Admin Kecamatan Invalid',
                'username' => 'admin-kecamatan-invalid',
                'email' => 'admin-kecamatan-invalid@example.com',
                'nomor_telepon' => '081234567892',
                'instansi' => 'Kantor Kecamatan Fiktif',
                'kecamatan' => 'Kecamatan Fiktif',
                'alamat_lengkap' => 'Jl. Validasi Kecamatan No. 1',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'status_verifikasi' => 'pending',
            ])
            ->assertRedirect(route('super.admins.create'))
            ->assertSessionHasErrors('kecamatan');

        $this->assertDatabaseMissing('admins', [
            'username' => 'admin-kecamatan-invalid',
        ]);
    }

    public function test_super_admin_cannot_update_manager_with_invalid_kecamatan(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $admin = $this->createAdmin($superAdmin, 'Admin Update Kecamatan', 'active');

        $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admins.edit', $admin))
            ->put(route('super.admins.update', $admin), [
                'nama' => 'Admin Update Kecamatan',
                'username' => (string) $admin->username,
                'email' => (string) $admin->email,
                'nomor_telepon' => '081234567893',
                'instansi' => 'Kantor Kecamatan Fiktif',
                'kecamatan' => 'Kecamatan Fiktif',
                'alamat_lengkap' => 'Jl. Validasi Kecamatan No. 2',
                'password' => '',
                'password_confirmation' => '',
                'status_verifikasi' => 'active',
            ])
            ->assertRedirect(route('super.admins.edit', $admin))
            ->assertSessionHasErrors('kecamatan');

        $this->assertSame('Lohbener', $admin->fresh()?->kecamatan);
    }

    public function test_manager_account_validation_rejects_duplicate_identity_and_password_mismatch(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $this->createAdmin($superAdmin, 'Admin Existing', 'active');

        $response = $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admins.create'))
            ->post(route('super.admins.store'), [
                'nama' => '',
                'username' => 'admin-existing',
                'email' => 'admin-existing@example.com',
                'nomor_telepon' => '123',
                'instansi' => '',
                'kecamatan' => '',
                'alamat_lengkap' => '',
                'password' => 'password123',
                'password_confirmation' => 'password456',
                'status_verifikasi' => 'owner',
            ]);

        $response->assertRedirect(route('super.admins.create'));
        $response->assertSessionHasErrors([
            'nama',
            'username',
            'email',
            'nomor_telepon',
            'instansi',
            'kecamatan',
            'alamat_lengkap',
            'password',
            'status_verifikasi',
        ]);
    }

    public function test_directory_routes_require_super_admin_guard(): void
    {
        $admin = $this->createAdmin(null, 'Admin Guard Direktori', 'active');

        $this->get(route('super.admins.create'))
            ->assertRedirect(route('super.login'));

        $regularAdmin = $this->createAdmin(null, 'Admin Biasa Guard', 'active');

        $this->actingAs($regularAdmin, 'admin')
            ->get(route('super.admins.show', $admin))
            ->assertRedirect(route('super.login'));
    }

    public function test_verification_page_renders_confirmation_modal_attributes(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $this->createAdmin($superAdmin, 'Admin Modal Verifikasi', 'pending');
        $this->createAdmin($superAdmin, 'Admin Modal Nonaktif', 'active');

        $this->actingAs($superAdmin, 'super_admin')
            ->get(route('super.admin-verifications.index', ['status' => 'semua']))
            ->assertOk()
            ->assertSee('Verifikasi Akun Pengelola?')
            ->assertSee('Tolak Verifikasi Akun?')
            ->assertSee('Nonaktifkan Akun Pengelola?')
            ->assertSee('Ya, Verifikasi')
            ->assertSee('Ya, Tolak')
            ->assertSee('Ya, Nonaktifkan');
    }

    private function createSuperAdmin(
        string $email = 'admin-directory-super@example.com',
        string $username = 'admin-directory-super'
    ): SuperAdmin {
        return SuperAdmin::query()->create([
            'nama' => 'Super Admin Directory',
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('password123'),
        ]);
    }

    private function createAdmin(?SuperAdmin $superAdmin, string $name, string $status): Admin
    {
        return Admin::query()->create([
            'super_admin_id' => $superAdmin?->id,
            'nama' => $name,
            'email' => str($name)->slug('-').'@example.com',
            'username' => (string) str($name)->slug('-'),
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi '.$name,
            'kecamatan' => str_contains($name, 'Sindang') ? 'Sindang' : 'Lohbener',
            'alamat_lengkap' => 'Jl. Direktori No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === 'pending' ? null : now(),
        ]);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function validManagerPayload(array $overrides = []): array
    {
        return array_merge([
            'nama' => 'Admin Payload',
            'username' => 'admin-payload',
            'email' => 'admin-payload@example.com',
            'nomor_telepon' => '081234567890',
            'instansi' => 'Kantor Kecamatan Lohbener',
            'kecamatan' => 'Lohbener',
            'alamat_lengkap' => 'Jl. Direktori No. 1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status_verifikasi' => Admin::STATUS_PENDING,
        ], $overrides);
    }
}
