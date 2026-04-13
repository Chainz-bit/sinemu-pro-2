<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge([
            'login' => $request->input('login') !== null ? (string) $request->input('login') : null,
            'password' => $request->input('password') !== null ? (string) $request->input('password') : null,
        ]);

        $validated = $request->validate(
            [
                'login' => 'required|string',
                'password' => 'required|string',
            ],
            [
                'login.required' => 'Email atau username wajib diisi.',
                'login.string' => 'Email atau username harus berupa teks.',
                'password.required' => 'Kata sandi wajib diisi.',
                'password.string' => 'Kata sandi harus berupa teks.',
            ]
        );

        $loginInput = trim((string) $validated['login']);
        $loginField = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $admin = Admin::query()->where($loginField, $loginInput)->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Email/username atau kata sandi tidak sesuai.']);
        }

        if (($admin->status_verifikasi ?? 'pending') === 'pending') {
            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => 'Akun Anda belum diverifikasi oleh super admin.']);
        }

        if ($admin->status_verifikasi === 'rejected') {
            $message = 'Akun Anda ditolak oleh super admin.';
            if (!empty($admin->alasan_penolakan)) {
                $message .= ' Alasan: '.$admin->alasan_penolakan;
            }

            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => $message]);
        }

        Auth::guard('admin')->login($admin, (bool) $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
