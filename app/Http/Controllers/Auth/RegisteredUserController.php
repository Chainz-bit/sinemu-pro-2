<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'password' => $request->input('password') !== null ? (string) $request->input('password') : null,
            'password_confirmation' => $request->input('password_confirmation') !== null ? (string) $request->input('password_confirmation') : null,
            'nomor_telepon' => $request->input('nomor_telepon') !== null ? trim((string) $request->input('nomor_telepon')) : null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'nomor_telepon' => ['required', 'string', 'regex:/^(08[0-9]{8,13}|\+628[0-9]{8,13})$/'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'nomor_telepon.regex' => 'Nomor telepon harus menggunakan format 08xxxxxxxxxx atau +628xxxxxxxxxx.',
        ]);

        $username = $this->buildUniqueUsername($validated['name'], $validated['email']);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $username,
            'email' => $validated['email'],
            'nomor_telepon' => $validated['nomor_telepon'],
            'password' => Hash::make($validated['password']),
        ]);

        // Email verification is not used; accounts are active after registration.
        $user->forceFill(['email_verified_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function buildUniqueUsername(string $name, string $email): string
    {
        $base = Str::slug($name, '');

        if ($base === '') {
            $base = Str::before($email, '@');
        }

        $base = Str::lower(preg_replace('/[^a-zA-Z0-9]/', '', $base) ?? 'pengguna');
        if ($base === '') {
            $base = 'pengguna';
        }

        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base.$counter;
            $counter++;
        }

        return $username;
    }
}
