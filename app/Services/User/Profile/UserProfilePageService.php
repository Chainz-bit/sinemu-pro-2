<?php

namespace App\Services\User\Profile;

use App\Models\User;
use App\Services\Common\ProfileAvatarService;

class UserProfilePageService
{
    public function __construct(
        private readonly ProfileAvatarService $avatarService,
        private readonly UserProfileActivityService $activityService,
        private readonly UserProfileStatsService $statsService
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function buildProfileData(User $user): array
    {
        $stats = $this->statsService->build((int) $user->id);
        [$verificationLabel, $verificationClass] = $this->resolveVerificationStatus($user);

        return [
            'laporanDiajukan' => $stats['laporanDiajukan'],
            'klaimMenunggu' => $stats['klaimMenunggu'],
            'klaimSelesai' => $stats['klaimSelesai'],
            'recentActivities' => $this->activityService->buildRecentActivities((int) $user->id),
            'profileAvatar' => $this->avatarService->resolve((string) ($user->profil ?? '')),
            'verificationLabel' => $verificationLabel,
            'verificationClass' => $verificationClass,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveVerificationStatus(User $user): array
    {
        if (!is_null($user->email_verified_at)) {
            return ['Terverifikasi', 'is-active'];
        }

        return ['Belum Verifikasi', 'is-pending'];
    }
}
