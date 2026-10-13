<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GoogleLoginRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Services\Google\GoogleIdTokenConfigurationException;
use App\Services\Google\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'],
            'nama' => $validated['name'],
            'email' => strtolower((string) $validated['email']),
            'nomor_telepon' => $validated['phone'],
            'password' => Hash::make((string) $validated['password']),
            'username' => $validated['username'],
            'alamat' => $validated['alamat'] ?? null,
            'email_verified_at' => now(),
        ];

        $user = User::query()->create($payload);
        $token = $user->createToken('mobile')->plainTextToken;

        return $this->mobileRegisterResponse($user, $token);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $login = trim((string) $validated['login']);
        $loginField = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::query()
            ->where($loginField, $loginField === 'email' ? strtolower($login) : $login)
            ->first();

        if (! $user || ! Hash::check((string) $validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Email/username atau kata sandi tidak sesuai.'],
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return $this->mobileLoginResponse($user, $token);
    }

    public function loginWithGoogle(GoogleLoginRequest $request, GoogleIdTokenVerifier $verifier): JsonResponse
    {
        $validated = $request->validated();

        try {
            $payload = $verifier->verify((string) $validated['id_token']);
        } catch (GoogleIdTokenConfigurationException $exception) {
            Log::error('Google Sign-In configuration error.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Konfigurasi Google Sign-In belum lengkap.',
            ], 500);
        } catch (Throwable $exception) {
            Log::warning('Google ID token verification failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Token Google tidak valid.',
            ], 401);
        }

        if (! is_array($payload)) {
            return response()->json([
                'message' => 'Token Google tidak valid.',
            ], 401);
        }

        $googleId = trim((string) ($payload['sub'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $name = trim((string) ($payload['name'] ?? ''));
        $avatar = trim((string) ($payload['picture'] ?? $payload['avatar'] ?? ''));

        if ($googleId === '') {
            return response()->json([
                'message' => 'Token Google tidak valid.',
            ], 401);
        }

        if ($email === '') {
            return response()->json([
                'message' => 'Email Google tidak tersedia.',
            ], 422);
        }

        if ($name === '') {
            $name = Str::before($email, '@') ?: $email;
        }

        $user = DB::transaction(function () use ($email, $name, $googleId, $avatar): User {
            $user = User::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            $linkedUser = User::query()
                ->where('google_id', $googleId)
                ->when($user, fn ($query) => $query->whereKeyNot($user->getKey()))
                ->first();

            abort_if($linkedUser !== null, 409, 'Akun Google sudah terhubung dengan user lain.');

            if ($user) {
                $this->syncGoogleUser($user, $googleId, $avatar);

                return $user->refresh();
            }

            return User::query()->create($this->newGoogleUserPayload($email, $name, $googleId, $avatar));
        });

        $token = $user->createToken('mobile')->plainTextToken;

        return $this->mobileLoginResponse($user, $token);
    }

    public function logout(): JsonResponse
    {
        $token = request()->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }

    private function syncGoogleUser(User $user, string $googleId, string $avatar): void
    {
        $updates = [];

        $currentGoogleId = trim((string) ($user->google_id ?? ''));

        abort_if(
            $currentGoogleId !== '' && $currentGoogleId !== $googleId,
            403,
            'Akun Google tidak sesuai dengan akun ini.'
        );

        if ($currentGoogleId === '') {
            $updates['google_id'] = $googleId;
        }

        if ($avatar !== '' && (string) ($user->avatar ?? '') !== $avatar) {
            $updates['avatar'] = $avatar;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function newGoogleUserPayload(string $email, string $name, string $googleId, string $avatar): array
    {
        $payload = [
            'name' => $name,
            'nama' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'username' => $this->makeUniqueUsername($email, $name),
            'email_verified_at' => now(),
            'google_id' => $googleId,
        ];

        if ($avatar !== '') {
            $payload['avatar'] = $avatar;
        }

        return $payload;
    }

    private function makeUniqueUsername(string $email, string $name): string
    {
        $source = trim((string) Str::before($email, '@')) ?: $name;
        $base = Str::of($source)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->limit(40, '')
            ->toString();

        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $counter = 1;

        while (User::query()->where('username', $username)->exists()) {
            $suffix = (string) $counter++;
            $username = Str::limit($base, 40 - strlen($suffix), '').$suffix;
        }

        return $username;
    }

    private function mobileLoginResponse(User $user, string $token): JsonResponse
    {
        return response()->json([
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) ($user->name ?? $user->nama ?? ''),
                'email' => (string) $user->email,
                'username' => $user->username,
            ],
        ]);
    }

    private function mobileRegisterResponse(User $user, string $token): JsonResponse
    {
        return response()->json([
            'message' => 'Registrasi berhasil',
            'token' => $token,
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) ($user->name ?? $user->nama ?? ''),
                'email' => (string) $user->email,
                'username' => $user->username,
                'phone' => $user->nomor_telepon,
                'alamat' => $user->alamat,
            ],
        ], 201);
    }
}
