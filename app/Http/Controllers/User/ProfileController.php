<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\User\Profile\UserProfilePageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly UserProfilePageService $profileService) {}

    public function index(): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user, 403);
        $data = $this->profileService->buildProfileData($user);

        return view('user.pages.profile.show', [
            'user' => $user,
            'laporanDiajukan' => $data['laporanDiajukan'],
            'klaimMenunggu' => $data['klaimMenunggu'],
            'klaimSelesai' => $data['klaimSelesai'],
            'recentActivities' => $data['recentActivities'],
            'profileAvatar' => $data['profileAvatar'],
            'verificationLabel' => $data['verificationLabel'],
            'verificationClass' => $data['verificationClass'],
        ]);
    }
}
