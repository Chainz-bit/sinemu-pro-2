<?php

use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\BarangWilayahController;
use App\Http\Controllers\Admin\Auth\RegisteredUserController as AdminRegisteredUserController;
use App\Http\Controllers\Admin\ClaimVerificationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FoundItemController;
use App\Http\Controllers\Admin\InputItemController;
use App\Http\Controllers\Admin\LostItemController;
use App\Http\Controllers\Admin\MatchingController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SettingsController;
use App\Support\ManagerPortal;
use Illuminate\Support\Facades\Route;

Route::prefix(ManagerPortal::urlPrefix())->name(ManagerPortal::routePrefix() . '.')->group(function () {
    Route::get('logout', [LoginController::class, 'redirectAfterLogoutGet'])->name('logout.get');

    Route::middleware(ManagerPortal::guestMiddleware())->group(function () {
        Route::get('register', [AdminRegisteredUserController::class, 'create'])->name('register');
        Route::post('register', [AdminRegisteredUserController::class, 'store'])->name('register.store');

        Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [LoginController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login.store');
    });

    Route::middleware(ManagerPortal::middleware())->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::patch('dashboard/reports/{type}/{id}', [DashboardController::class, 'updateReport'])->name('dashboard.reports.update');
        Route::post('dashboard/reports/{type}/{id}/publish-home', [DashboardController::class, 'publishToHome'])->name('dashboard.reports.publish-home');

        Route::get('barang-hilang', [LostItemController::class, 'index'])->name('lost-items');
        Route::middleware(ManagerPortal::regionMiddleware())->group(function () {
            Route::get('barang-hilang/{laporanBarangHilang}', [LostItemController::class, 'show'])->name('lost-items.show');
            Route::get('barang-hilang/{laporanBarangHilang}/edit', [LostItemController::class, 'edit'])->name('lost-items.edit');
            Route::patch('barang-hilang/{laporanBarangHilang}', [LostItemController::class, 'update'])->name('lost-items.update');
            Route::patch('barang-hilang/{laporanBarangHilang}/status', [LostItemController::class, 'updateStatus'])->name('lost-items.update-status');
            Route::patch('barang-hilang/{laporanBarangHilang}/verify', [LostItemController::class, 'verify'])->name('lost-items.verify');
            Route::delete('barang-hilang/{laporanBarangHilang}', [LostItemController::class, 'destroy'])->name('lost-items.destroy');
        });

        Route::get('barang-temuan', [FoundItemController::class, 'index'])->name('found-items');
        Route::middleware(ManagerPortal::regionMiddleware())->group(function () {
            Route::get('barang-temuan/{barang}', [FoundItemController::class, 'show'])->name('found-items.show');
            Route::get('barang-temuan/{barang}/edit', [FoundItemController::class, 'edit'])->name('found-items.edit');
            Route::patch('barang-temuan/{barang}', [FoundItemController::class, 'update'])->name('found-items.update');
            Route::get('barang-temuan/{barang}/export', [FoundItemController::class, 'export'])->name('found-items.export');
            Route::patch('barang-temuan/{barang}/status', [FoundItemController::class, 'updateStatus'])->name('found-items.update-status');
            Route::patch('barang-temuan/{barang}/verify', [FoundItemController::class, 'verify'])->name('found-items.verify');
            Route::delete('barang-temuan/{barang}', [FoundItemController::class, 'destroy'])->name('found-items.destroy');
        });

        Route::post('pencocokan', [MatchingController::class, 'store'])->name('matches.store');
        Route::post('pencocokan/tidak-cocok', [MatchingController::class, 'dismiss'])->name('matches.dismiss');

        Route::get('verifikasi-klaim', [ClaimVerificationController::class, 'index'])->name('claim-verifications');
        Route::get('verifikasi-klaim/{klaim}', [ClaimVerificationController::class, 'show'])->name('claim-verifications.show');
        Route::delete('verifikasi-klaim/{klaim}', [ClaimVerificationController::class, 'destroy'])->name('claim-verifications.destroy');
        Route::post('verifikasi-klaim/{klaim}/approve', [ClaimVerificationController::class, 'approve'])->name('claim-verifications.approve');
        Route::post('verifikasi-klaim/{klaim}/reject', [ClaimVerificationController::class, 'reject'])->name('claim-verifications.reject');
        Route::post('verifikasi-klaim/{klaim}/complete', [ClaimVerificationController::class, 'complete'])->name('claim-verifications.complete');

        Route::get('input-barang', [InputItemController::class, 'index'])->name('input-items');
        Route::post('input-barang', [InputItemController::class, 'store'])->name('input-items.store');

        Route::get('barang-wilayah', [BarangWilayahController::class, 'index'])->name('barang-wilayah.index');
        Route::middleware(ManagerPortal::regionMiddleware())->group(function () {
            Route::get('barang-wilayah/{barang}/edit', [BarangWilayahController::class, 'edit'])->name('barang-wilayah.edit');
            Route::put('barang-wilayah/{barang}', [BarangWilayahController::class, 'update'])->name('barang-wilayah.update');
            Route::delete('barang-wilayah/{barang}', [BarangWilayahController::class, 'destroy'])->name('barang-wilayah.destroy');
        });

        Route::get('profil', [ProfileController::class, 'index'])->name('profile');
        Route::get('profil/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profil', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('pengaturan', [SettingsController::class, 'index'])->name('settings');
        Route::put('pengaturan', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('pengaturan/log-aktivitas', [SettingsController::class, 'logs'])->name('settings.logs');

        Route::get('notifications', function () {
            return redirect()->route(\App\Support\ManagerPortal::dashboardRoute());
        })->name('notifications.index');
        Route::post('notifications/read-all', [AdminNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [AdminNotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::delete('notifications/{notification}', [AdminNotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::delete('notifications', [AdminNotificationController::class, 'destroyAll'])->name('notifications.destroy-all');

        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    });
});

Route::prefix(ManagerPortal::legacyUrlPrefix())->group(function () {
    Route::get('{path?}', function (?string $path = null) {
        $query = request()->getQueryString();

        return redirect(ManagerPortal::legacyRedirectTarget($path) . ($query ? '?' . $query : ''));
    })->where('path', '.*');
});
