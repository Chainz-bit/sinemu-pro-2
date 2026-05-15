<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_admin_login_and_register_screens_can_be_rendered(): void
    {
        $this->get(route('admin.login'))->assertOk();
        $this->get(route('admin.register'))->assertOk();
    }

    public function test_admin_routes_use_pengelola_barang_public_urls(): void
    {
        $this->assertSame(url('/pengelola-barang/login'), route('admin.login'));
        $this->assertSame(url('/pengelola-barang/register'), route('admin.register'));
        $this->assertSame(url('/pengelola-barang/dashboard'), route('admin.dashboard'));

        $this->get('/admin/login')
            ->assertRedirect('/pengelola-barang/login');
    }

    public function test_manager_view_namespace_points_to_admin_views(): void
    {
        $this->assertTrue(View::exists('manager::auth.login'));
        $this->assertTrue(View::exists('manager::auth.register'));
        $this->assertTrue(View::exists('manager::pages.dashboard.index'));
        $this->assertTrue(View::exists('admin::auth.login'));
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

    public function test_active_admin_can_login_with_email(): void
    {
        $admin = $this->createAdmin([
            'username' => 'admin-login-email',
            'email' => 'admin-login-email@example.com',
            'status_verifikasi' => 'active',
        ]);

        $response = $this->post(route('admin.login'), [
            'login' => 'admin-login-email@example.com',
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
            'login' => 'Akun Anda masih menunggu verifikasi super admin.',
        ]);
        $this->assertGuest('admin');
    }

    public function test_rejected_admin_cannot_login(): void
    {
        $this->createAdmin([
            'username' => 'admin-rejected',
            'email' => 'admin-rejected@example.com',
            'status_verifikasi' => 'rejected',
        ]);

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login'), [
                'login' => 'admin-rejected',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'login' => 'Akun Anda ditolak atau memerlukan perbaikan data.',
        ]);
        $this->assertGuest('admin');
    }

    public function test_inactive_admin_cannot_login(): void
    {
        $this->createAdmin([
            'username' => 'admin-inactive',
            'email' => 'admin-inactive@example.com',
            'status_verifikasi' => 'inactive',
        ]);

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login'), [
                'login' => 'admin-inactive',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'login' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi super admin.',
        ]);
        $this->assertGuest('admin');
    }

    public function test_deleted_admin_cannot_login(): void
    {
        $admin = $this->createAdmin([
            'username' => 'admin-deleted',
            'email' => 'admin-deleted@example.com',
            'status_verifikasi' => 'active',
        ]);

        $admin->delete();

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login'), [
                'login' => 'admin-deleted',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'login' => 'Email/username atau kata sandi tidak sesuai.',
        ]);
        $this->assertGuest('admin');
    }

    public function test_user_account_cannot_login_through_manager_portal(): void
    {
        User::factory()->create([
            'email' => 'regular-user@example.com',
            'username' => 'regular-user',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login'), [
                'login' => 'regular-user@example.com',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'login' => 'Email/username atau kata sandi tidak sesuai.',
        ]);
        $this->assertGuest('admin');
        $this->assertGuest('web');
    }

    public function test_super_admin_account_cannot_login_through_manager_portal(): void
    {
        SuperAdmin::query()->create([
            'nama' => 'Super Admin Login Portal',
            'email' => 'super-through-manager@example.com',
            'username' => 'super-through-manager',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login'), [
                'login' => 'super-through-manager@example.com',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'login' => 'Email/username atau kata sandi tidak sesuai.',
        ]);
        $this->assertGuest('admin');
        $this->assertGuest('super_admin');
    }

    public function test_guest_cannot_access_manager_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_user_cannot_access_manager_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'regular-dashboard-user@example.com',
            'username' => 'regular-dashboard-user',
        ]);

        $this->actingAs($user, 'web');

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_authenticated_super_admin_cannot_access_manager_dashboard(): void
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Dashboard Portal',
            'email' => 'super-dashboard-manager@example.com',
            'username' => 'super-dashboard-manager',
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($superAdmin, 'super_admin');

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_pending_admin_cannot_access_manager_dashboard(): void
    {
        $admin = $this->createAdmin([
            'username' => 'admin-pending-dashboard',
            'email' => 'admin-pending-dashboard@example.com',
            'status_verifikasi' => 'pending',
        ]);

        $this->actingAs($admin, 'admin');

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors([
                'login' => 'Akun Anda masih menunggu verifikasi super admin.',
            ]);

        $this->assertGuest('admin');
    }

    public function test_rejected_admin_cannot_access_manager_dashboard(): void
    {
        $admin = $this->createAdmin([
            'username' => 'admin-rejected-dashboard',
            'email' => 'admin-rejected-dashboard@example.com',
            'status_verifikasi' => 'rejected',
        ]);

        $this->actingAs($admin, 'admin');

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors([
                'login' => 'Akun Anda ditolak atau memerlukan perbaikan data.',
            ]);

        $this->assertGuest('admin');
    }

    public function test_authenticated_admin_is_logged_out_when_status_is_no_longer_active(): void
    {
        $admin = $this->createAdmin([
            'username' => 'admin-active-then-inactive',
            'email' => 'admin-active-then-inactive@example.com',
            'status_verifikasi' => 'active',
        ]);

        $this->actingAs($admin, 'admin');

        $admin->update(['status_verifikasi' => 'inactive']);

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors([
                'login' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi super admin.',
            ]);

        $this->assertGuest('admin');
    }

    public function test_admin_registration_creates_pending_admin_with_unique_username(): void
    {
        $response = $this->post(route('admin.register'), array_merge(
            $this->validAdminRegistrationPayload(),
            [
                'status_verifikasi' => 'active',
                'role' => 'super_admin',
                'super_admin_id' => 999,
            ]
        ));

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status', 'Pendaftaran pengelola barang berhasil. Akun Anda akan aktif setelah diverifikasi super admin.');

        $this->assertDatabaseHas('admins', [
            'nama' => 'Admin Baru',
            'email' => 'admin-baru@example.com',
            'nomor_telepon' => '081234567890',
            'username' => 'admin-baru',
            'status_verifikasi' => 'pending',
            'super_admin_id' => null,
        ]);

        $admin = Admin::query()->where('username', 'admin-baru')->firstOrFail();
        $this->assertTrue(Hash::check('password123', (string) $admin->password));
        $this->assertNotSame('password123', $admin->password);
        $this->assertArrayNotHasKey('role', $admin->getAttributes());
    }

    public function test_admin_registration_rejects_duplicate_username_without_server_error(): void
    {
        $this->createAdmin([
            'username' => 'angga',
            'email' => 'existing-username@example.com',
            'status_verifikasi' => 'active',
        ]);

        $response = $this->from(route('admin.register'))
            ->post(route('admin.register'), array_merge($this->validAdminRegistrationPayload(), [
                'username' => 'angga',
                'email' => 'angga-baru@example.com',
            ]));

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.register'));
        $response->assertSessionHasErrors([
            'username' => 'Username sudah digunakan.',
        ]);
        $this->assertDatabaseMissing('admins', ['email' => 'angga-baru@example.com']);
    }

    public function test_admin_registration_rejects_duplicate_email_without_server_error(): void
    {
        $this->createAdmin([
            'username' => 'existing-email-admin',
            'email' => 'duplikat-admin@example.com',
            'status_verifikasi' => 'active',
        ]);

        $response = $this->from(route('admin.register'))
            ->post(route('admin.register'), array_merge($this->validAdminRegistrationPayload(), [
                'username' => 'email-baru',
                'email' => 'duplikat-admin@example.com',
            ]));

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.register'));
        $response->assertSessionHasErrors([
            'email' => 'Email sudah digunakan sebagai akun pengelola.',
        ]);
        $this->assertDatabaseMissing('admins', ['username' => 'email-baru']);
    }

    public function test_admin_registration_rejects_invalid_phone_number(): void
    {
        $response = $this->from(route('admin.register'))
            ->post(route('admin.register'), array_merge($this->validAdminRegistrationPayload(), [
                'nomor_telepon' => 'abc',
            ]));

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.register'));
        $response->assertSessionHasErrors([
            'nomor_telepon' => 'Nomor telepon harus menggunakan format 08xxxxxxxxxx atau +628xxxxxxxxxx.',
        ]);
        $this->assertDatabaseMissing('admins', ['username' => 'admin-baru']);
    }

    public function test_admin_registration_rejects_password_confirmation_mismatch(): void
    {
        $response = $this->from(route('admin.register'))
            ->post(route('admin.register'), array_merge($this->validAdminRegistrationPayload(), [
                'password_confirmation' => 'password456',
            ]));

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.register'));
        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('admins', ['username' => 'admin-baru']);
    }

    public function test_admin_login_validates_required_fields(): void
    {
        $response = $this->from(route('admin.login'))
            ->post(route('admin.login'), []);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors(['login', 'password']);
    }

    public function test_admin_can_logout_and_cannot_access_dashboard_after_logout(): void
    {
        $admin = $this->createAdmin([
            'username' => 'admin-logout',
            'email' => 'admin-logout@example.com',
            'status_verifikasi' => 'active',
        ]);
        $token = 'admin-logout-token';

        $this->actingAs($admin, 'admin');

        $this->withSession(['_token' => $token])
            ->post(route('admin.logout'), ['_token' => $token])
            ->assertRedirect(route('home'));

        $this->assertGuest('admin');

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_get_admin_logout_does_not_logout_admin(): void
    {
        $admin = $this->createAdmin([
            'username' => 'admin-get-logout',
            'email' => 'admin-get-logout@example.com',
            'status_verifikasi' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.logout', absolute: false))
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_get_admin_logout_as_guest_redirects_without_token_mismatch(): void
    {
        $this->get(route('admin.logout.get', absolute: false))
            ->assertRedirect(route('home'));

        $this->assertGuest('admin');
    }

    public function test_manager_login_route_uses_throttle_middleware(): void
    {
        $route = Route::getRoutes()->match(request()->create('/pengelola-barang/login', 'POST'));

        $this->assertContains('throttle:5,1', $route->gatherMiddleware());
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

    /**
     * @return array<string, string>
     */
    private function validAdminRegistrationPayload(): array
    {
        return [
            'nama' => 'Admin Baru',
            'email' => 'admin-baru@example.com',
            'nomor_telepon' => ' 081234567890 ',
            'username' => 'admin-baru',
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Admin Baru No. 1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }
}
