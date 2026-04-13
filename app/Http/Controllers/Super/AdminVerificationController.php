<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminVerificationController extends Controller
{
    public function index(): View
    {
        $admins = Admin::query()
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('super.admin-verifications.index', compact('admins'));
    }

    public function accept(Admin $admin): RedirectResponse
    {
        $admin->update([
            'status_verifikasi' => 'active',
            'alasan_penolakan' => null,
            'verified_at' => now(),
        ]);

        return back()->with('status', 'Admin berhasil diverifikasi dan diaktifkan.');
    }

    public function reject(Request $request, Admin $admin): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_penolakan' => ['nullable', 'string', 'max:1200'],
        ]);

        $admin->update([
            'status_verifikasi' => 'rejected',
            'alasan_penolakan' => $validated['alasan_penolakan'] ?? null,
            'verified_at' => now(),
        ]);

        return back()->with('status', 'Admin ditolak.');
    }
}
