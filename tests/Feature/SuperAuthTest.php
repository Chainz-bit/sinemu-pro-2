<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_login_screen_can_be_rendered(): void
    {
        $this->get(route('super.login'))->assertOk();
    }

    public function test_super_admin_can_login_with_email_or_username(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $emailResponse = $this->post(route('super.login.store'), [
            'login' => 'super-auth@example.com',
            'password' => 'password123',
        ]);

        $emailResponse->assertRedirect(route('super.dashboard'));
        $this->assertAuthenticatedAs($superAdmin, 'super_admin');

        auth('super_admin')->logout();
        $this->app['session']->invalidate();
        $this->app['session']->regenerateToken();

        $usernameResponse = $this->post(route('super.login.store'), [
            'login' => 'super-auth',
            'password' => 'password123',
        ]);

        $usernameResponse->assertRedirect(route('super.dashboard'));
        $this->assertAuthenticatedAs($superAdmin, 'super_admin');
    }

    public function test_super_login_rejects_invalid_credentials_with_generic_error(): void
    {
        $this->createSuperAdmin();

        $wrongPasswordResponse = $this->from(route('super.login'))
            ->post(route('super.login.store'), [
                'login' => 'super-auth@example.com',
                'password' => 'wrong-password',
            ]);

        $wrongPasswordResponse->assertRedirect(route('super.login'));
        $wrongPasswordResponse->assertSessionHasErrors([
            'login' => 'Kredensial super admin tidak valid.',
        ]);
        $this->assertGuest('super_admin');

        $unknownAccountResponse = $this->from(route('super.login'))
            ->post(route('super.login.store'), [
                'login' => 'unknown-super@example.com',
                'password' => 'password123',
            ]);

        $unknownAccountResponse->assertRedirect(route('super.login'));
        $unknownAccountResponse->assertSessionHasErrors([
            'login' => 'Kredensial super admin tidak valid.',
        ]);
        $this->assertGuest('super_admin');
    }

    public function test_super_login_validates_required_fields(): void
    {
        $response = $this->from(route('super.login'))
            ->post(route('super.login.store'), []);

        $response->assertRedirect(route('super.login'));
        $response->assertSessionHasErrors(['login', 'password']);
        $this->assertGuest('super_admin');
    }

    public function test_regular_user_and_admin_cannot_login_through_super_login(): void
    {
        User::factory()->create([
            'email' => 'regular-user@example.com',
            'password' => Hash::make('password123'),
        ]);

        Admin::query()->create([
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
        ]);

        $this->from(route('super.login'))
            ->post(route('super.login.store'), [
                'login' => 'regular-user@example.com',
                'password' => 'password123',
            ])
            ->assertRedirect(route('super.login'));

        $this->from(route('super.login'))
            ->post(route('super.login.store'), [
                'login' => 'admin-auth',
                'password' => 'password123',
            ])
            ->assertRedirect(route('super.login'));

        $this->assertGuest('super_admin');
    }

    public function test_super_dashboard_requires_super_authentication(): void
    {
        $this->get(route('super.dashboard'))
            ->assertRedirect(route('super.login'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('super.dashboard'))
            ->assertRedirect(route('super.login'));

        $admin = Admin::query()->create([
            'super_admin_id' => null,
            'nama' => 'Admin Dashboard Auth',
            'email' => 'admin-dashboard-auth@example.com',
            'nomor_telepon' => '081111111119',
            'username' => 'admin-dashboard-auth',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Dashboard Admin No. 1',
            'status_verifikasi' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('super.dashboard'))
            ->assertRedirect(route('super.login'));
    }

    public function test_authenticated_super_admin_is_redirected_away_from_login_screen(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin, 'super_admin')
            ->get(route('super.login'))
            ->assertRedirect(route('super.dashboard'));
    }

    public function test_super_logout_invalidates_super_session(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = 'super-logout-token';

        $this->actingAs($superAdmin, 'super_admin')
            ->withSession(['_token' => $token])
            ->post(route('super.logout'), ['_token' => $token])
            ->assertRedirect(route('home'));

        $this->assertGuest('super_admin');
        $this->get(route('super.dashboard'))
            ->assertRedirect(route('super.login'));
    }

    public function test_get_super_logout_does_not_logout_super_admin(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin, 'super_admin')
            ->get(route('super.logout', absolute: false))
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($superAdmin, 'super_admin');
    }

    public function test_get_super_logout_as_guest_redirects_without_token_mismatch(): void
    {
        $this->get(route('super.logout.get', absolute: false))
            ->assertRedirect(route('home'));

        $this->assertGuest('super_admin');
    }

    public function test_super_login_is_throttled_after_repeated_failures(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt += 1) {
            $this->from(route('super.login'))
                ->post(route('super.login.store'), [
                    'login' => 'unknown-super@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('super.login'));
        }

        $this->from(route('super.login'))
            ->post(route('super.login.store'), [
                'login' => 'unknown-super@example.com',
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests();
    }

    public function test_super_login_form_contains_csrf_token(): void
    {
        $this->get(route('super.login'))
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_super_login_route_uses_expected_throttle_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('super.login.store');

        $this->assertNotNull($route);
        $this->assertContains('throttle:5,1', $route->gatherMiddleware());
    }

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'nama' => 'Super Auth',
            'email' => 'super-auth@example.com',
            'username' => 'super-auth',
            'password' => Hash::make('password123'),
        ]);
    }
}
