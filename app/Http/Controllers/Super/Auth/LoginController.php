<?php

namespace App\Http\Controllers\Super\Auth;

use App\Http\Controllers\Controller;
use App\Services\Support\DatabaseHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly DatabaseHealthService $databaseHealthService
    ) {
    }

    public function create(): View|RedirectResponse
    {
        if ($this->isDatabaseResponsive() && Auth::guard('super_admin')->check()) {
            return redirect()->route('super.dashboard');
        }

        return view('super.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'login' => $request->input('login') !== null ? (string) $request->input('login') : null,
            'password' => $request->input('password') !== null ? (string) $request->input('password') : null,
        ]);

        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (!$this->isDatabaseResponsive()) {
            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => 'Layanan autentikasi sedang tidak responsif. Coba lagi beberapa saat.']);
        }

        $loginInput = trim($validated['login']);
        $normalizedLogin = strtolower($loginInput);
        $loginField = filter_var($normalizedLogin, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [
            $loginField => $normalizedLogin,
            'password' => $validated['password'],
        ];

        if (Auth::guard('super_admin')->attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->route('super.dashboard');
        }

        return back()
            ->withInput($request->only('login'))
            ->withErrors(['login' => 'Kredensial super admin tidak valid.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('super_admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super.login');
    }

    private function isDatabaseResponsive(): bool
    {
        return $this->databaseHealthService->isResponsive();
    }
}
