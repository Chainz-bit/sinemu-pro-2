<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_profile_information(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.profile.update'), [
                'nama' => 'Admin Operasional Baru',
                'email' => 'admin-baru@example.com',
                'instansi' => 'Kampus SINEMU Pusat',
                'kecamatan' => 'Indramayu Kota',
                'alamat_lengkap' => 'Jl. Operasional Baru No. 10',
            ]);

        $response->assertRedirect(route('admin.profile'));
        $response->assertSessionHas('status', 'Profil admin berhasil diperbarui.');

        $admin->refresh();

        $this->assertSame('Admin Operasional Baru', $admin->nama);
        $this->assertSame('admin-baru@example.com', $admin->email);
        $this->assertSame('Kampus SINEMU Pusat', $admin->instansi);
        $this->assertSame('Indramayu Kota', $admin->kecamatan);
        $this->assertSame('Jl. Operasional Baru No. 10', $admin->alamat_lengkap);
    }

    public function test_admin_profile_update_validates_required_fields(): void
    {
        $admin = $this->createAdmin();

        $response = $this->from(route('admin.profile.edit'))
            ->actingAs($admin, 'admin')
            ->put(route('admin.profile.update'), [
                'email' => 'not-an-email',
            ]);

        $response->assertRedirect(route('admin.profile.edit'));
        $response->assertSessionHasErrors([
            'nama',
            'email',
            'instansi',
            'kecamatan',
            'alamat_lengkap',
        ]);

        $this->assertSame('profile-admin@example.com', $admin->fresh()?->email);
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Profile Update',
            'email' => 'profile-update-super@example.com',
            'username' => 'profile-update-super',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Profile Update',
            'email' => 'profile-admin@example.com',
            'username' => 'profile-admin-update',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Profil Admin No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
