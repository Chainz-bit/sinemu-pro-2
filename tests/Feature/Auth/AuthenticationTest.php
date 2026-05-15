<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_auth_routes_are_not_excluded_from_csrf_verification(): void
    {
        $csrfMiddleware = $this->app->make(ValidateCsrfToken::class);

        $this->assertSame([], $csrfMiddleware->getExcludedPaths());
    }

    public function test_users_can_authenticate_using_username(): void
    {
        $user = User::factory()->create([
            'username' => 'usertester',
        ]);

        $response = $this->post('/login', [
            'login' => 'usertester',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_with_numeric_password_input(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('12345678'),
        ]);

        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 12345678,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();
        $token = 'logout-token';

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => $token])
            ->post('/logout', ['_token' => $token]);

        $this->assertGuest();
        $response->assertRedirect('/');
        $this->assertNotSame('/logout', $response->headers->get('Location'));
    }

    public function test_get_logout_does_not_logout_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/logout');

        $response->assertRedirect(route('home', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_get_logout_as_guest_redirects_without_token_mismatch(): void
    {
        $this->get('/logout')
            ->assertRedirect(route('home', absolute: false));

        $this->assertGuest();
    }
}
