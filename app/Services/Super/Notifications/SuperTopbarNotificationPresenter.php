<?php

namespace App\Services\Super\Notifications;

use App\Models\Admin;
use App\Support\AdminVerificationStatusPresenter;

class SuperTopbarNotificationPresenter
{
    /**
     * @return array{
     *   title: string,
     *   message: string,
     *   action_url: string,
     *   created_at: mixed,
     *   is_urgent: bool,
     *   tag: string
     * }
     */
    public function pending(Admin $admin): array
    {
        return [
            'title' => 'Admin menunggu verifikasi',
            'message' => sprintf(
                '%s dari %s perlu ditinjau sekarang.',
                (string) $admin->nama,
                (string) ($admin->instansi ?: 'instansi belum diisi')
            ),
            'action_url' => route('super.admin-verifications.index', ['search' => $admin->nama]),
            'created_at' => $admin->created_at,
            'is_urgent' => true,
            'tag' => 'Perlu tindakan',
        ];
    }

    /**
     * @return array{
     *   title: string,
     *   message: string,
     *   action_url: string,
     *   created_at: mixed,
     *   is_urgent: bool,
     *   tag: string
     * }
     */
    public function activity(Admin $admin): array
    {
        $statusKey = AdminVerificationStatusPresenter::key($admin->status_verifikasi);
        $statusLabel = AdminVerificationStatusPresenter::label($statusKey);
        $activityTime = $admin->verified_at ?? $admin->updated_at ?? $admin->created_at;

        return [
            'title' => sprintf('Status admin %s', $statusLabel),
            'message' => sprintf(
                '%s dari %s masuk ke status %s.',
                (string) $admin->nama,
                (string) ($admin->instansi ?: 'instansi belum diisi'),
                strtolower($statusLabel)
            ),
            'action_url' => route('super.admins.index', ['search' => $admin->nama]),
            'created_at' => $activityTime,
            'is_urgent' => false,
            'tag' => 'Aktivitas',
        ];
    }
}
