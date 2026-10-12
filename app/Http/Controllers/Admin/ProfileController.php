<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Models\Admin;
use App\Services\Admin\Profile\AdminProfilePageService;
use App\Support\ManagerPortal;
use App\Support\RoleLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AdminProfilePageService $profileService) {}

    public function index(): View
    {
        /** @var Admin|null $admin */
        $admin = ManagerPortal::user();
        abort_if(! $admin, 403);
        $data = $this->profileService->buildProfileData($admin);

        return view('manager::pages.profile.show', [
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
        /** @var Admin|null $admin */
        $admin = ManagerPortal::user();
        abort_if(! $admin, 403);
        $data = $this->profileService->buildEditData($admin);

        return view('manager::pages.profile.edit', [
            'admin' => $admin,
            'profileAvatar' => $data['profileAvatar'],
            'verificationLabel' => $data['verificationLabel'],
            'verificationClass' => $data['verificationClass'],
            'kecamatanOptions' => $data['kecamatanOptions'],
        ]);
    }

    public function update(UpdateAdminProfileRequest $request): RedirectResponse
    {
        /** @var Admin|null $admin */
        $admin = ManagerPortal::user();
        abort_if(! $admin, 403);
        $this->profileService->update($admin, $request->validated(), $request->file('profil'));

        return redirect()
            ->route(ManagerPortal::routeName('profile'))
            ->with('status', 'Profil '.RoleLabels::managerLower().' berhasil diperbarui.');
    }
}
