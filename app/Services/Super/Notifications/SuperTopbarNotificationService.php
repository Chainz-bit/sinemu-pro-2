<?php

namespace App\Services\Super\Notifications;

use App\Services\Super\Admins\AdminVerificationQueryService;
use App\Support\AdminVerificationStatusPresenter;
use Illuminate\Support\Collection;

class SuperTopbarNotificationService
{
    private const MAX_ITEMS = 8;

    public function __construct(
        private readonly AdminVerificationQueryService $adminVerificationQueryService,
        private readonly SuperTopbarNotificationPresenter $notificationPresenter
    ) {
    }

    /**
     * @return array{
     *   notifications: Collection<int, array{
     *     title: string,
     *     message: string,
     *     action_url: string,
     *     created_at: mixed,
     *     is_urgent: bool,
     *     tag: string
     *   }>,
     *   unreadCount: int
     * }
     */
    public function build(): array
    {
        $pendingAdmins = $this->adminVerificationQueryService->buildPendingPreview(self::MAX_ITEMS);
        $latestActivities = $this->adminVerificationQueryService->buildLatestActivities(self::MAX_ITEMS);

        $pendingNotifications = $pendingAdmins->map(
            fn ($admin) => $this->notificationPresenter->pending($admin)
        );

        $activityNotifications = $latestActivities
            ->filter(function ($admin) {
                return AdminVerificationStatusPresenter::key($admin->status_verifikasi) !== 'pending';
            })
            ->map(fn ($admin) => $this->notificationPresenter->activity($admin));

        $notifications = $pendingNotifications
            ->concat($activityNotifications)
            ->sortByDesc('created_at')
            ->take(self::MAX_ITEMS)
            ->values();

        return [
            'notifications' => $notifications,
            'unreadCount' => (int) $pendingAdmins->count(),
        ];
    }
}
