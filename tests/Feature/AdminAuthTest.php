<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_and_register_screens_can_be_rendered(): void
    {
        $this->get(route('admin.login'))->assertOk();
        $this->get(route('admin.register'))->assertOk();
    }

    public function test_active_admin_can_login_with_username(): void
    {
        $admin = $this->createAdmin([
            'username' => 'admin-login',
            'email' => 'admin-login@example.com',
            'status_verifikasi' => 'active',
        ]);

        $response = $this->post(route('admin.login'), [
            'login' => 'admin-login',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
        $this->assertSame($admin->id, auth('admin')->id());
    }

    public function test_pending_admin_cannot_login(): void
    {
        $this->createAdmin([
            'username' => 'admin-pending',
            'email' => 'admin-pending@example.com',
            'status_verifikasi' => 'pending',
        ]);

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login'), [
                'login' => 'admin-pending',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'login' => 'Akun admin belum aktif. Tunggu verifikasi dari super admin.',
        ]);
        $this->assertGuest('admin');
    }

    public function test_admin_registration_creates_pending_admin_with_unique_username(): void
    {
        $this->createAdmin([
            'username' => 'adminbaru',
            'email' => 'existing-admin@example.com',
            'status_verifikasi' => 'active',
        ]);

        $response = $this->post(route('admin.register'), [
            'nama' => 'Admin Baru',
            'email' => 'admin-baru@example.com',
            'nomor_telepon' => ' 081234567890 ',
            'username' => 'Admin Baru',
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Admin Baru No. 1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status', 'Pendaftaran admin berhasil. Akun Anda akan aktif setelah diverifikasi super admin.');

        $this->assertDatabaseHas('admins', [
            'nama' => 'Admin Baru',
            'email' => 'admin-baru@example.com',
            'nomor_telepon' => '081234567890',
            'username' => 'adminbaru1',
            'status_verifikasi' => 'pending',
        ]);
    }

    public function test_admin_login_validates_required_fields(): void
    {
        $response = $this->from(route('admin.login'))
            ->post(route('admin.login'), []);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors(['login', 'password']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createAdmin(array $overrides = []): Admin
    {
        return Admin::query()->create(array_merge([
            'super_admin_id' => null,
            'nama' => 'Admin Auth',
            'email' => 'admin-auth@example.com',
            'nomor_telepon' => '081111111118',
            'username' => 'admin-auth',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Auth Admin No. 1',
            'status_verifikasi' => 'active',
        ], $overrides));
    }
}
