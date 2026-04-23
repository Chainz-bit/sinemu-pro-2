<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
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
            'email' => str($name)->slug('-') . '@example.com',
            'username' => (string) str($name)->slug('-'),
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi ' . $name,
            'kecamatan' => str_contains($name, 'Sindang') ? 'Sindang' : 'Lohbener',
            'alamat_lengkap' => 'Jl. Direktori No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === 'pending' ? null : now(),
        ]);
    }
}
