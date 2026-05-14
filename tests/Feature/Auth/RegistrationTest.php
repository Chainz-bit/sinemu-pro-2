<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'nomor_telepon' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user?->email_verified_at);
        $this->assertTrue(Hash::check('password', (string) $user?->password));
    }

    public function test_new_users_can_register_with_numeric_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'Numeric User',
            'email' => 'numeric@example.com',
            'nomor_telepon' => '089912345678',
            'password' => 12345678,
            'password_confirmation' => 12345678,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_users_can_register_with_international_indonesian_phone_format(): void
    {
        $response = $this->post('/register', [
            'name' => 'International Phone User',
            'email' => 'international@example.com',
            'nomor_telepon' => '+6281234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create([
            'email' => 'duplicate@example.com',
        ]);

        $response = $this->post('/register', [
            'name' => 'Duplicate User',
            'email' => 'duplicate@example.com',
            'nomor_telepon' => '081234567891',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Mismatch User',
            'email' => 'mismatch@example.com',
            'nomor_telepon' => '081234567892',
            'password' => 'password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_registration_requires_required_fields(): void
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors([
            'name',
            'email',
            'nomor_telepon',
            'password',
        ]);
        $this->assertGuest();
    }

    public function test_registration_rejects_invalid_phone_number(): void
    {
        $response = $this->post('/register', [
            'name' => 'Invalid Phone User',
            'email' => 'invalid-phone@example.com',
            'nomor_telepon' => 'abc',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('nomor_telepon');
        $this->assertGuest();
    }

    public function test_registration_does_not_allow_role_injection(): void
    {
        $response = $this->post('/register', [
            'name' => 'Role Injection User',
            'email' => 'role-injection@example.com',
            'nomor_telepon' => '081234567893',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'super_admin',
            'is_admin' => true,
            'is_super_admin' => true,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'role-injection@example.com')->firstOrFail();

        $this->assertArrayNotHasKey('role', $user->getAttributes());
        $this->assertArrayNotHasKey('is_admin', $user->getAttributes());
        $this->assertArrayNotHasKey('is_super_admin', $user->getAttributes());
    }

    public function test_register_route_uses_throttle_middleware(): void
    {
        $route = Route::getRoutes()->match(request()->create('/register', 'POST'));

        $this->assertContains('throttle:5,1', $route->gatherMiddleware());
    }

    public function test_register_route_is_not_excluded_from_csrf_verification(): void
    {
        $csrfMiddleware = $this->app->make(ValidateCsrfToken::class);

        $this->assertNotContains('register', $csrfMiddleware->getExcludedPaths());
    }
}
