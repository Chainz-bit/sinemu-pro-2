<?php

namespace App\View\Composers;

use App\Services\Support\DatabaseHealthService;
use App\Support\ManagerPortal;
use Illuminate\View\View;

class AdminTopbarComposer
{
    private const TOPBAR_NOTIFICATIONS_LIMIT = 8;

    public function __construct(
        private readonly DatabaseHealthService $databaseHealthService
    ) {}

    public function compose(View $view): void
    {
        $viewData = $view->getData();
        if (($viewData['hideTopActions'] ?? false) === true || ! $this->databaseHealthService->isResponsive()) {
            $this->bindEmptyState($view);

            return;
        }

        $admin = ManagerPortal::user();
        if (! $admin) {
            $this->bindEmptyState($view);

            return;
        }

        $notifications = $admin->notifications()
            ->select(['id', 'admin_id', 'title', 'message', 'action_url', 'read_at', 'created_at'])
            ->latest('created_at')
            ->limit(self::TOPBAR_NOTIFICATIONS_LIMIT)
            ->get();

        $unreadCount = $admin->notifications()
            ->whereNull('read_at')
            ->count();

        $view->with('adminNotifications', $notifications)
            ->with('adminUnreadNotificationsCount', $unreadCount)
            ->with('managerNotifications', $notifications)
            ->with('managerUnreadNotificationsCount', $unreadCount);
    }

    private function bindEmptyState(View $view): void
    {
        $view->with('adminNotifications', collect())
            ->with('adminUnreadNotificationsCount', 0)
            ->with('managerNotifications', collect())
            ->with('managerUnreadNotificationsCount', 0);
    }
}
