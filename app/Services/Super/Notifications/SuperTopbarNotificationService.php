<?php

namespace App\Services\Super\Notifications;

use App\Models\SuperNotificationDismissal;
use App\Services\Super\Admins\AdminVerificationQueryService;
use App\Support\AdminVerificationStatusPresenter;
use Illuminate\Support\Collection;

class SuperTopbarNotificationService
{
    private const MAX_ITEMS = 8;

    public function __construct(
        private readonly AdminVerificationQueryService $adminVerificationQueryService,
        private readonly SuperTopbarNotificationPresenter $notificationPresenter
    ) {}

    /**
     * @return array{
     *   notifications: Collection<int, array{
     *     title: string,
     *     message: string,
     *     action_url: string,
     *     created_at: mixed,
     *     is_urgent: bool,
     *     tag: string,
     *     item_key: string,
     *     is_dismissible: bool
     *   }>,
     *   unreadCount: int
     * }
     */
    public function build(?int $superAdminId = null): array
    {
        if ($superAdminId === null) {
            return $this->emptyState();
        }

        $pendingAdmins = $this->adminVerificationQueryService->buildPendingPreview(self::MAX_ITEMS, $superAdminId);
        $pendingNotifications = $pendingAdmins->map(
            fn ($admin) => $this->notificationPresenter->pending($admin)
        );

        $activityNotifications = $this->buildVisibleActivityNotifications($superAdminId);

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

    /**
     * @return Collection<int,string>
     */
    public function visibleActivityItemKeys(int $superAdminId): Collection
    {
        return $this->build($superAdminId)['notifications']
            ->filter(fn (array $notification) => ($notification['is_dismissible'] ?? false) === true)
            ->pluck('item_key')
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{
     *   title: string,
     *   message: string,
     *   action_url: string,
     *   created_at: mixed,
     *   is_urgent: bool,
     *   tag: string,
     *   item_key: string,
     *   is_dismissible: bool
     * }>
     */
    private function buildVisibleActivityNotifications(int $superAdminId): Collection
    {
        $activityNotifications = $this->adminVerificationQueryService
            ->buildLatestActivities(self::MAX_ITEMS, $superAdminId)
            ->filter(function ($admin) {
                return AdminVerificationStatusPresenter::key($admin->status_verifikasi) !== 'pending';
            })
            ->map(fn ($admin) => $this->notificationPresenter->activity($admin))
            ->values();

        $itemKeys = $activityNotifications
            ->pluck('item_key')
            ->filter()
            ->values();

        if ($itemKeys->isEmpty()) {
            return $activityNotifications;
        }

        $dismissedItemKeys = SuperNotificationDismissal::query()
            ->where('super_admin_id', $superAdminId)
            ->whereIn('item_key', $itemKeys->all())
            ->pluck('item_key')
            ->all();

        return $activityNotifications
            ->reject(fn (array $notification) => in_array((string) ($notification['item_key'] ?? ''), $dismissedItemKeys, true))
            ->values();
    }

    /**
     * @return array{
     *   notifications: Collection<int, array{
     *     title: string,
     *     message: string,
     *     action_url: string,
     *     created_at: mixed,
     *     is_urgent: bool,
     *     tag: string,
     *     item_key: string,
     *     is_dismissible: bool
     *   }>,
     *   unreadCount: int
     * }
     */
    private function emptyState(): array
    {
        return [
            'notifications' => collect(),
            'unreadCount' => 0,
        ];
    }
}
