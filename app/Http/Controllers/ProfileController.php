<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 403);

        $profilePath = trim((string) ($user->profil ?? ''));
        if ($profilePath === '') {
            $profileAvatar = asset('img/profil.jpg');
        } elseif (str_starts_with($profilePath, 'http://') || str_starts_with($profilePath, 'https://')) {
            $profileAvatar = $profilePath;
        } elseif (str_starts_with($profilePath, '/')) {
            $profileAvatar = asset(ltrim($profilePath, '/'));
        } else {
            $profileAvatar = asset('storage/' . ltrim($profilePath, '/'));
        }

        $verificationLabel = !is_null($user->email_verified_at) ? 'Terverifikasi' : 'Belum Verifikasi';
        $verificationClass = !is_null($user->email_verified_at) ? 'is-active' : 'is-pending';

        return view('user.pages.profile-edit', compact(
            'user',
            'profileAvatar',
            'verificationLabel',
            'verificationClass'
        ));
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validated();
        unset($validated['profil']);
        if (!Schema::hasColumn('users', 'nomor_telepon')) {
            unset($validated['nomor_telepon']);
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $photo = $request->file('profil');
        if ($photo) {
            $oldProfilePath = trim((string) ($user->profil ?? ''));
            if (
                $oldProfilePath !== ''
                && !str_starts_with($oldProfilePath, 'http://')
                && !str_starts_with($oldProfilePath, 'https://')
                && !str_starts_with($oldProfilePath, '/')
            ) {
                Storage::disk('public')->delete($oldProfilePath);
            }

            $user->profil = $photo->store('profil-user/' . now()->format('Y/m'), 'public');
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'Profil user berhasil diperbarui.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}