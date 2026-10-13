<?php

namespace App\Services\Super\Notifications;

use App\Models\Admin;
use App\Support\AdminVerificationStatusPresenter;
use App\Support\RoleLabels;

class SuperTopbarNotificationPresenter
{
    /**
     * @return array{
     *   title: string,
     *   message: string,
     *   action_url: string,
     *   created_at: mixed,
     *   is_urgent: bool,
     *   tag: string,
     *   item_key: string,
     *   is_dismissible: bool
     * }
     */
    public function pending(Admin $admin): array
    {
        $managerRoleLabelLower = RoleLabels::managerLower();

        return [
            'title' => ucfirst($managerRoleLabelLower).' menunggu verifikasi',
            'message' => sprintf(
                '%s dari %s perlu ditinjau sekarang.',
                (string) $admin->nama,
                (string) ($admin->instansi ?: 'instansi belum diisi')
            ),
            'action_url' => route('super.admin-verifications.index', ['search' => $admin->nama]),
            'created_at' => $admin->created_at,
            'is_urgent' => true,
            'tag' => 'Perlu tindakan',
            'item_key' => sprintf(
                'admin_pending:%d:%d',
                (int) $admin->id,
                (int) ($admin->created_at?->getTimestamp() ?? 0)
            ),
            'is_dismissible' => false,
        ];
    }

    /**
     * @return array{
     *   title: string,
     *   message: string,
     *   action_url: string,
     *   created_at: mixed,
     *   is_urgent: bool,
     *   tag: string,
     *   item_key: string,
     *   is_dismissible: bool
     * }
     */
    public function activity(Admin $admin): array
    {
        $statusKey = AdminVerificationStatusPresenter::key($admin->status_verifikasi);
        $statusLabel = AdminVerificationStatusPresenter::label($statusKey);
        $activityTime = $admin->verified_at ?? $admin->updated_at ?? $admin->created_at;

        return [
            'title' => sprintf('Status %s %s', RoleLabels::managerLower(), $statusLabel),
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
            'item_key' => sprintf(
                'admin_status:%d:%s:%d',
                (int) $admin->id,
                $statusKey,
                (int) ($admin->updated_at?->getTimestamp() ?? 0)
            ),
            'is_dismissible' => true,
        ];
    }
}
