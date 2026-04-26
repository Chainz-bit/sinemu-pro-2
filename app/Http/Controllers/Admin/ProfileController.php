<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Models\Admin;
use App\Services\Admin\Profile\AdminProfilePageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AdminProfilePageService $profileService)
    {
    }

    public function index(): View
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        abort_if(!$admin, 403);
        $data = $this->profileService->buildProfileData($admin);

        return view('admin.pages.profile', [
            'admin' => $admin,
            'laporanDiajukan' => $data['laporanDiajukan'],
            'klaimMenunggu' => $data['klaimMenunggu'],
            'selesaiDitangani' => $data['selesaiDitangani'],
            'recentActivities' => $data['recentActivities'],
            'profileAvatar' => $data['profileAvatar'],
            'verificationLabel' => $data['verificationLabel'],
            'verificationClass' => $data['verificationClass'],
        ]);
    }

    public function edit(): View
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        abort_if(!$admin, 403);
        $data = $this->profileService->buildEditData($admin);

        return view('admin.pages.profile-edit', [
            'admin' => $admin,
            'profileAvatar' => $data['profileAvatar'],
            'verificationLabel' => $data['verificationLabel'],
            'verificationClass' => $data['verificationClass'],
            'kecamatanOptions' => $data['kecamatanOptions'],
        ]);
    }

    public function update(UpdateAdminProfileRequest $request): RedirectResponse
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        abort_if(!$admin, 403);
        $this->profileService->update($admin, $request->validated(), $request->file('profil'));

        return redirect()
            ->route('admin.profile')
            ->with('status', 'Profil admin berhasil diperbarui.');
    }
}
