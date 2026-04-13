<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{
    public function markAllAsRead(): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin, 403);

        $admin->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function markAsRead(AdminNotification $notification): RedirectResponse
    {
        $adminId = (int) Auth::guard('admin')->id();
        abort_if((int) $notification->admin_id !== $adminId, 403);

        if (is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }

        return back();
    }

    public function destroy(AdminNotification $notification): RedirectResponse
    {
        $adminId = (int) Auth::guard('admin')->id();
        abort_if((int) $notification->admin_id !== $adminId, 403);

        $notification->delete();

        return back();
    }

    public function destroyAll(): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin, 403);

        $admin->notifications()->delete();

        return back();
    }
}
