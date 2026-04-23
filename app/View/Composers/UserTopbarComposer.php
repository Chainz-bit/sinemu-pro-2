<?php

namespace App\View\Composers;

use App\Services\Support\DatabaseHealthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserTopbarComposer
{
    private const TOPBAR_NOTIFICATIONS_LIMIT = 8;

    public function __construct(
        private readonly DatabaseHealthService $databaseHealthService
    ) {
    }

    public function compose(View $view): void
    {
        $viewData = $view->getData();
        if (($viewData['hideTopActions'] ?? false) === true || !$this->databaseHealthService->isResponsive()) {
            $this->bindEmptyState($view);
            return;
        }

        $user = Auth::user();
        if (!$user) {
            $this->bindEmptyState($view);
            return;
        }

        $notifications = $user->notifications()
            ->select(['id', 'user_id', 'title', 'message', 'action_url', 'read_at', 'created_at'])
            ->latest('created_at')
            ->limit(self::TOPBAR_NOTIFICATIONS_LIMIT)
            ->get();

        $unreadCount = $user->notifications()
            ->whereNull('read_at')
            ->count();

        $view->with('userNotifications', $notifications)
            ->with('userUnreadNotificationsCount', $unreadCount);
    }

    private function bindEmptyState(View $view): void
    {
        $view->with('userNotifications', collect())
            ->with('userUnreadNotificationsCount', 0);
    }
}
