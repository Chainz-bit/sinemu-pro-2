<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\Admin;
use App\Support\ManagerPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('manager::auth.login');
    }

    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $loginInput = $request->loginInput();
        $loginField = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $admin = Admin::query()->where($loginField, $loginInput)->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Email/username atau kata sandi tidak sesuai.']);
        }

        $status = (string) ($admin->status_verifikasi ?? 'pending');
        if ($status !== 'active') {
            $message = match ($status) {
                'rejected' => 'Akun Anda ditolak atau memerlukan perbaikan data.',
                'inactive' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi super admin.',
                default => 'Akun Anda masih menunggu verifikasi super admin.',
            };

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => $message]);
        }

        Auth::guard(ManagerPortal::guard())->login($admin, (bool) $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route(ManagerPortal::dashboardRoute()));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard(ManagerPortal::guard())->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function redirectAfterLogoutGet(Request $request): RedirectResponse
    {
        return redirect()->route('home');
    }
}
