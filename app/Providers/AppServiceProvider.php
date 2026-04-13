<?php

namespace App\Providers;

use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Observers\BarangObserver;
use App\Observers\KlaimObserver;
use App\Observers\LaporanBarangHilangObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // Tambahkan ini
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paksa skema HTTP jika di lingkungan local/produksi tanpa SSL
        if (config('app.env') === 'local' || config('app.env') === 'production') {
            URL::forceScheme('http');
        }

        // Hubungkan proses bisnis dengan notifikasi admin.
        LaporanBarangHilang::observe(LaporanBarangHilangObserver::class);
        Barang::observe(BarangObserver::class);
        Klaim::observe(KlaimObserver::class);

        // Data notifikasi global untuk semua halaman admin.
        View::composer('admin.partials.topbar', function ($view) {
            $admin = Auth::guard('admin')->user();

            if (!$admin) {
                $view->with('adminNotifications', collect())
                    ->with('adminUnreadNotificationsCount', 0);
                return;
            }

            $notifications = $admin->notifications()
                ->latest('created_at')
                ->get();

            $unreadCount = $admin->notifications()
                ->whereNull('read_at')
                ->count();

            $view->with('adminNotifications', $notifications)
                ->with('adminUnreadNotificationsCount', $unreadCount);
        });
    }
}
