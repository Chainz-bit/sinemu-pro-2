<?php

namespace App\Providers;

use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Observers\BarangObserver;
use App\Observers\KlaimObserver;
use App\Observers\LaporanBarangHilangObserver;
use App\View\Composers\AdminTopbarComposer;
use App\View\Composers\SuperTopbarComposer;
use App\View\Composers\UserTopbarComposer;
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

        View::composer('admin.partials.topbar', AdminTopbarComposer::class);
        View::composer('user.partials.topbar', UserTopbarComposer::class);
        View::composer('super.partials.topbar', SuperTopbarComposer::class);
    }
}
