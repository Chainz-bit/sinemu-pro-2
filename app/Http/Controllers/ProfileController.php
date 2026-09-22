<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\User\Profile\UserProfileEditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly UserProfileEditService $profileService)
    {
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        abort_unless($request->user() !== null, 403);
        $user = $request->user();
        $data = $this->profileService->buildEditData($user);
        $profileAvatar = $data['profileAvatar'];
        $verificationLabel = $data['verificationLabel'];
        $verificationClass = $data['verificationClass'];

        return view('user.pages.profile.edit', compact(
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
        abort_unless($request->user() !== null, 403);
        $user = $request->user();
        $this->profileService->update($user, $request);

        return Redirect::route('profile.edit')->with('status', 'Profil pengguna berhasil diperbarui.');
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
