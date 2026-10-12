<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Http\Requests\Super\UpdateProfileRequest;
use App\Models\SuperAdmin;
use App\Services\Super\Profile\SuperProfileCommandService;
use App\Services\Super\Profile\SuperProfileQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly SuperProfileQueryService $queryService,
        private readonly SuperProfileCommandService $commandService
    ) {}

    public function index(): View
    {
        $superAdmin = $this->currentSuperAdmin();
        $data = $this->queryService->buildProfileData($superAdmin);

        return view('super.pages.profile.show', [
            'superAdmin' => $superAdmin,
            ...$data,
        ]);
    }

    public function edit(): View
    {
        $superAdmin = $this->currentSuperAdmin();
        $data = $this->queryService->buildEditData($superAdmin);

        return view('super.pages.profile.edit', [
            'superAdmin' => $superAdmin,
            ...$data,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $superAdmin = $this->currentSuperAdmin();
        $this->commandService->update($superAdmin, $request);

        return redirect()
            ->route('super.profile')
            ->with('status', 'Profil super admin berhasil diperbarui.');
    }

    private function currentSuperAdmin(): SuperAdmin
    {
        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = Auth::guard('super_admin')->user();
        abort_if(! $superAdmin, 403);

        return $superAdmin;
    }
}
