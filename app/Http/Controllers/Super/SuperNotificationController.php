<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\SuperNotificationDismissal;
use App\Services\Super\Notifications\SuperTopbarNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SuperNotificationController extends Controller
{
    public function __construct(
        private readonly SuperTopbarNotificationService $superTopbarNotificationService
    ) {
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_key' => ['required', 'string', 'max:255'],
        ]);

        SuperNotificationDismissal::query()->updateOrCreate(
            [
                'super_admin_id' => $this->superAdminId(),
                'item_key' => $validated['item_key'],
            ],
            ['dismissed_at' => now()]
        );

        return back()->with('status', 'Riwayat notifikasi disembunyikan.');
    }

    public function dismissActivities(): RedirectResponse
    {
        $superAdminId = $this->superAdminId();
        $itemKeys = $this->superTopbarNotificationService->visibleActivityItemKeys($superAdminId);

        if ($itemKeys->isEmpty()) {
            return back()->with('status', 'Tidak ada riwayat notifikasi yang perlu dibersihkan.');
        }

        $now = now();
        $rows = $itemKeys->map(fn (string $itemKey) => [
            'super_admin_id' => $superAdminId,
            'item_key' => $itemKey,
            'dismissed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        SuperNotificationDismissal::query()->upsert(
            $rows,
            ['super_admin_id', 'item_key'],
            ['dismissed_at', 'updated_at']
        );

        return back()->with('status', 'Riwayat notifikasi berhasil dibersihkan dari topbar.');
    }

    public function dismissed(): View
    {
        $dismissals = SuperNotificationDismissal::query()
            ->where('super_admin_id', $this->superAdminId())
            ->latest('dismissed_at')
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('super.pages.notifications.dismissed', compact('dismissals'));
    }

    public function restore(SuperNotificationDismissal $dismissal): RedirectResponse
    {
        abort_if((int) $dismissal->super_admin_id !== $this->superAdminId(), 404);

        $dismissal->delete();

        return back()->with('status', 'Riwayat notifikasi berhasil ditampilkan kembali.');
    }

    public function restoreAll(): RedirectResponse
    {
        SuperNotificationDismissal::query()
            ->where('super_admin_id', $this->superAdminId())
            ->delete();

        return back()->with('status', 'Semua riwayat notifikasi berhasil ditampilkan kembali.');
    }

    private function superAdminId(): int
    {
        $superAdminId = Auth::guard('super_admin')->id();
        abort_if($superAdminId === null, 403);

        return (int) $superAdminId;
    }
}
