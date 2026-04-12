<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\RegisteredUserController as AdminRegisteredUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LostItemController;
use App\Http\Controllers\Admin\ClaimVerificationController;
use App\Http\Controllers\Admin\FoundItemController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\InputItemController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\UserItemActionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/media/{folder}/{path}', [MediaController::class, 'show'])
    ->whereIn('folder', ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'])
    ->where('path', '.*')
    ->name('media.image');
Route::middleware('auth')->get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/laporan/hilang', [UserItemActionController::class, 'storeLostReport'])->name('user.lost-reports.store');
    Route::post('/laporan/temuan', [UserItemActionController::class, 'storeFoundReport'])->name('user.found-reports.store');
    Route::post('/klaim', [UserItemActionController::class, 'storeClaim'])->name('user.claims.store');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('register', [AdminRegisteredUserController::class, 'create'])->name('register');
        Route::post('register', [AdminRegisteredUserController::class, 'store']);

        Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [LoginController::class, 'login']);
    });
    

    Route::middleware('admin')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('barang-hilang', [LostItemController::class, 'index'])->name('lost-items');
        Route::get('barang-hilang/{laporanBarangHilang}', [LostItemController::class, 'show'])->name('lost-items.show');
        Route::delete('barang-hilang/{laporanBarangHilang}', [LostItemController::class, 'destroy'])->name('lost-items.destroy');
        Route::get('barang-temuan', [FoundItemController::class, 'index'])->name('found-items');
        Route::get('barang-temuan/{barang}', [FoundItemController::class, 'show'])->name('found-items.show');
        Route::get('barang-temuan/{barang}/export', [FoundItemController::class, 'export'])->name('found-items.export');
        Route::patch('barang-temuan/{barang}/status', [FoundItemController::class, 'updateStatus'])->name('found-items.update-status');
        Route::delete('barang-temuan/{barang}', [FoundItemController::class, 'destroy'])->name('found-items.destroy');
        Route::get('verifikasi-klaim', [ClaimVerificationController::class, 'index'])->name('claim-verifications');
        Route::get('verifikasi-klaim/{klaim}', [ClaimVerificationController::class, 'show'])->name('claim-verifications.show');
        Route::delete('verifikasi-klaim/{klaim}', [ClaimVerificationController::class, 'destroy'])->name('claim-verifications.destroy');
        Route::get('input-barang', [InputItemController::class, 'index'])->name('input-items');
        Route::post('input-barang', [InputItemController::class, 'store'])->name('input-items.store');
        Route::post('verifikasi-klaim/{klaim}/approve', [ClaimVerificationController::class, 'approve'])->name('claim-verifications.approve');
        Route::post('verifikasi-klaim/{klaim}/reject', [ClaimVerificationController::class, 'reject'])->name('claim-verifications.reject');
        Route::post('notifications/read-all', [AdminNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [AdminNotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    });
});

require __DIR__.'/auth.php';
