<?php

namespace App\Providers;

use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Observers\BarangObserver;
use App\Observers\KlaimObserver;
use App\Observers\LaporanBarangHilangObserver;
use App\Services\Google\GoogleApiClientIdTokenVerifier;
use App\Services\Google\GoogleIdTokenVerifier;
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
        $this->app->bind(GoogleIdTokenVerifier::class, GoogleApiClientIdTokenVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paksa skema HTTP jika di lingkungan local/produksi tanpa SSL
        if (config('app.env') === 'production' || config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Hubungkan proses bisnis dengan notifikasi admin.
        LaporanBarangHilang::observe(LaporanBarangHilangObserver::class);
        Barang::observe(BarangObserver::class);
        Klaim::observe(KlaimObserver::class);

        View::addNamespace('manager', resource_path('views/manager'));
        View::addNamespace('admin', resource_path('views/manager'));

        View::composer(['manager::*', 'admin::*'], function ($view): void {
            $data = $view->getData();

            if (array_key_exists('admin', $data) && !array_key_exists('manager', $data)) {
                $view->with('manager', $data['admin']);
            }

            if (array_key_exists('manager', $data) && !array_key_exists('admin', $data)) {
                $view->with('admin', $data['manager']);
            }
        });

        View::composer('manager::partials.topbar', AdminTopbarComposer::class);
        View::composer('admin::partials.topbar', AdminTopbarComposer::class);
        View::composer('admin.partials.topbar', AdminTopbarComposer::class);
        View::composer('user.partials.topbar', UserTopbarComposer::class);
        View::composer('super.partials.topbar', SuperTopbarComposer::class);
    }
}
