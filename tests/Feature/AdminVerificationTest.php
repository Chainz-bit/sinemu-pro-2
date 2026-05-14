<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_verification_page_shows_scoped_pending_admins_by_default(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $otherSuperAdmin = $this->createSuperAdmin(
            email: 'other-verification-super@example.com',
            username: 'other-verification-super'
        );

        $pendingAdmin = $this->createAdmin($superAdmin, 'Admin Pending', 'pending');
        $globalPendingAdmin = $this->createAdmin(null, 'Admin Global Pending', 'pending');
        $this->createAdmin($superAdmin, 'Admin Aktif', 'active');
        $this->createAdmin($otherSuperAdmin, 'Admin Super Lain', 'pending');

        $response = $this->actingAs($superAdmin, 'super_admin')->get(route('super.admin-verifications.index'));

        $response->assertOk();
        $response->assertViewHas('search', '');
        $response->assertViewHas('statusFilter', 'pending');

        $admins = $response->viewData('admins');

        $this->assertInstanceOf(LengthAwarePaginator::class, $admins);
        $this->assertSame(2, $admins->total());
        $this->assertEqualsCanonicalizing(
            [$pendingAdmin->id, $globalPendingAdmin->id],
            collect($admins->items())->pluck('id')->all()
        );
    }

    public function test_admin_verification_page_applies_search_and_status_filters(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $activeTarget = $this->createAdmin($superAdmin, 'Admin Kecamatan Sindang', 'active');
        $this->createAdmin($superAdmin, 'Admin Kecamatan Lohbener', 'active');
        $this->createAdmin($superAdmin, 'Admin Pending Sindang', 'pending');

        $response = $this->actingAs($superAdmin, 'super_admin')->get(route('super.admin-verifications.index', [
            'search' => 'Sindang',
            'status' => 'active',
        ]));

        $response->assertOk();
        $response->assertViewHas('search', 'Sindang');
        $response->assertViewHas('statusFilter', 'active');

        $admins = $response->viewData('admins');

        $this->assertInstanceOf(LengthAwarePaginator::class, $admins);
        $this->assertSame(1, $admins->total());
        $this->assertSame($activeTarget->id, collect($admins->items())->first()->id);
    }

    public function test_super_admin_can_accept_pending_admin_in_scope(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $admin = $this->createAdmin($superAdmin, 'Admin Akan Disetujui', 'pending');

        $response = $this
            ->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admin-verifications.index'))
            ->post(route('super.admin-verifications.accept', $admin));

        $response->assertRedirect(route('super.admin-verifications.index'));
        $response->assertSessionHas('status', 'Pengelola barang berhasil diverifikasi dan diaktifkan.');

        $admin->refresh();
        $this->assertSame('active', $admin->status_verifikasi);
        $this->assertNull($admin->alasan_penolakan);
        $this->assertSame((int) $superAdmin->id, (int) $admin->super_admin_id);
        $this->assertNotNull($admin->verified_at);
    }

    public function test_super_admin_can_reactivate_inactive_admin_but_cannot_verify_active_admin_again(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $inactiveAdmin = $this->createAdmin($superAdmin, 'Admin Akan Diaktifkan Ulang', 'inactive');
        $activeAdmin = $this->createAdmin($superAdmin, 'Admin Sudah Aktif', 'active');

        $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admin-verifications.index', ['status' => 'inactive']))
            ->patch(route('super.admins.verify', $inactiveAdmin))
            ->assertRedirect(route('super.admin-verifications.index', ['status' => 'inactive']))
            ->assertSessionHas('status', 'Pengelola barang berhasil diverifikasi dan diaktifkan.');

        $inactiveAdmin->refresh();
        $this->assertSame('active', $inactiveAdmin->status_verifikasi);
        $this->assertNotNull($inactiveAdmin->verified_at);

        $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admin-verifications.index', ['status' => 'active']))
            ->patch(route('super.admins.verify', $activeAdmin))
            ->assertRedirect(route('super.admin-verifications.index', ['status' => 'active']))
            ->assertSessionHas('error', 'Akun pengelola barang sudah aktif.');

        $this->assertSame('active', $activeAdmin->fresh()?->status_verifikasi);
    }

    public function test_super_admin_cannot_reject_admin_outside_scope(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $otherSuperAdmin = $this->createSuperAdmin(
            email: 'scope-owner@example.com',
            username: 'scope-owner'
        );
        $admin = $this->createAdmin($otherSuperAdmin, 'Admin Di Luar Cakupan', 'pending');

        $response = $this
            ->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admin-verifications.index'))
            ->post(route('super.admin-verifications.reject', $admin), [
                'alasan_penolakan' => 'Data tidak valid',
            ]);

        $response->assertRedirect(route('super.admin-verifications.index'));
        $response->assertSessionHas('error', 'Pengelola barang ini tidak berada dalam cakupan akun super admin Anda.');

        $admin->refresh();
        $this->assertSame('pending', $admin->status_verifikasi);
        $this->assertNull($admin->alasan_penolakan);
        $this->assertSame((int) $otherSuperAdmin->id, (int) $admin->super_admin_id);
    }

    public function test_reject_admin_validation_fails_when_reason_is_too_long(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $admin = $this->createAdmin($superAdmin, 'Admin Alasan Panjang', 'pending');
        $tooLongReason = str_repeat('a', 1201);

        $response = $this
            ->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admin-verifications.index'))
            ->post(route('super.admin-verifications.reject', $admin), [
                'alasan_penolakan' => $tooLongReason,
            ]);

        $response->assertRedirect(route('super.admin-verifications.index'));
        $response->assertSessionHasErrors('alasan_penolakan');

        $admin->refresh();
        $this->assertSame('pending', $admin->status_verifikasi);
        $this->assertNull($admin->alasan_penolakan);
    }

    public function test_super_admin_cannot_reject_or_deactivate_invalid_status_transition(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $activeAdmin = $this->createAdmin($superAdmin, 'Admin Aktif Tidak Ditolak', 'active');
        $pendingAdmin = $this->createAdmin($superAdmin, 'Admin Pending Tidak Dinonaktifkan', 'pending');

        $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admin-verifications.index', ['status' => 'active']))
            ->patch(route('super.admins.reject', $activeAdmin), [
                'alasan_penolakan' => 'Tidak boleh menolak akun aktif',
            ])
            ->assertRedirect(route('super.admin-verifications.index', ['status' => 'active']))
            ->assertSessionHas('error', 'Hanya akun pengelola barang yang masih menunggu verifikasi yang dapat ditolak.');

        $this->assertSame('active', $activeAdmin->fresh()?->status_verifikasi);
        $this->assertNull($activeAdmin->fresh()?->alasan_penolakan);

        $this->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admin-verifications.index', ['status' => 'pending']))
            ->patch(route('super.admins.deactivate', $pendingAdmin))
            ->assertRedirect(route('super.admin-verifications.index', ['status' => 'pending']))
            ->assertSessionHas('error', 'Hanya akun pengelola barang aktif yang dapat dinonaktifkan.');

        $this->assertSame('pending', $pendingAdmin->fresh()?->status_verifikasi);
    }

    public function test_super_admin_can_deactivate_admin_in_scope(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $admin = $this->createAdmin($superAdmin, 'Admin Akan Dinonaktifkan', 'active');

        $response = $this
            ->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admin-verifications.index', ['status' => 'active']))
            ->patch(route('super.admins.deactivate', $admin));

        $response->assertRedirect(route('super.admin-verifications.index', ['status' => 'active']));
        $response->assertSessionHas('status', 'Akun pengelola barang berhasil dinonaktifkan.');

        $admin->refresh();
        $this->assertSame('inactive', $admin->status_verifikasi);
        $this->assertNull($admin->alasan_penolakan);
    }

    public function test_super_admin_cannot_deactivate_admin_outside_scope(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $otherSuperAdmin = $this->createSuperAdmin(
            email: 'other-deactivate-owner@example.com',
            username: 'other-deactivate-owner'
        );
        $admin = $this->createAdmin($otherSuperAdmin, 'Admin Deactivate Luar Scope', 'active');

        $response = $this
            ->actingAs($superAdmin, 'super_admin')
            ->from(route('super.admin-verifications.index', ['status' => 'active']))
            ->patch(route('super.admins.deactivate', $admin));

        $response->assertRedirect(route('super.admin-verifications.index', ['status' => 'active']));
        $response->assertSessionHas('error', 'Pengelola barang ini tidak berada dalam cakupan akun super admin Anda.');

        $admin->refresh();
        $this->assertSame('active', $admin->status_verifikasi);
        $this->assertSame((int) $otherSuperAdmin->id, (int) $admin->super_admin_id);
    }

    private function createSuperAdmin(
        string $email = 'admin-verification-super@example.com',
        string $username = 'admin-verification-super'
    ): SuperAdmin {
        return SuperAdmin::query()->create([
            'nama' => 'Super Admin Verification',
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('password123'),
        ]);
    }

    private function createAdmin(?SuperAdmin $superAdmin, string $name, ?string $status): Admin
    {
        return Admin::query()->create([
            'super_admin_id' => $superAdmin?->id,
            'nama' => $name,
            'email' => str($name)->slug('-') . '@example.com',
            'username' => (string) str($name)->slug('-'),
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi ' . $name,
            'kecamatan' => str_contains($name, 'Sindang') ? 'Sindang' : 'Lohbener',
            'alamat_lengkap' => 'Jl. Verifikasi No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === 'pending' || $status === null ? null : now(),
        ]);
    }
}
