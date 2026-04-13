<?php

namespace App\Http\Controllers\Super\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('super_admin')->check()) {
            return redirect()->route('super.admin-verifications.index');
        }

        return view('super.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => $request->input('email') !== null ? (string) $request->input('email') : null,
            'password' => $request->input('password') !== null ? (string) $request->input('password') : null,
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::guard('super_admin')->attempt([
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
        ])) {
            $request->session()->regenerate();

            return redirect()->route('super.admin-verifications.index');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Kredensial super admin tidak valid.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('super_admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super.login');
    }
}
