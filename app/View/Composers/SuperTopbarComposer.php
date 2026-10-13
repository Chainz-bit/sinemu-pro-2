<?php

namespace App\View\Composers;

use App\Services\Super\Notifications\SuperTopbarNotificationService;
use App\Services\Support\DatabaseHealthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SuperTopbarComposer
{
    public function __construct(
        private readonly DatabaseHealthService $databaseHealthService,
        private readonly SuperTopbarNotificationService $superTopbarNotificationService
    ) {}

    public function compose(View $view): void
    {
        $viewData = $view->getData();
        if (($viewData['hideTopActions'] ?? false) === true || ! $this->databaseHealthService->isResponsive()) {
            $this->bindEmptyState($view);

            return;
        }

        if (! Auth::guard('super_admin')->check()) {
            $this->bindEmptyState($view);

            return;
        }

        $superAdminId = Auth::guard('super_admin')->id();

        if ($superAdminId === null) {
            $this->bindEmptyState($view);

            return;
        }

        $notificationData = $this->superTopbarNotificationService->build((int) $superAdminId);

        $view->with('superNotifications', $notificationData['notifications'])
            ->with('superUnreadNotificationsCount', $notificationData['unreadCount']);
    }

    private function bindEmptyState(View $view): void
    {
        $view->with('superNotifications', collect())
            ->with('superUnreadNotificationsCount', 0);
    }
}
