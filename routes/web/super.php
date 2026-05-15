<?php

use App\Http\Controllers\Super\AdminVerificationController;
use App\Http\Controllers\Super\AdminDirectoryController;
use App\Http\Controllers\Super\Auth\LoginController;
use App\Http\Controllers\Super\DashboardController;
use App\Http\Controllers\Super\ProfileController;
use App\Http\Controllers\Super\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('super')->name('super.')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
    Route::get('logout', [LoginController::class, 'redirectAfterLogoutGet'])->name('logout.get');

    Route::middleware('super')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('profile', [ProfileController::class, 'index'])->name('profile');
        Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('pengaturan', [SettingsController::class, 'index'])->name('settings');
        Route::put('pengaturan', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('pengaturan/riwayat', [SettingsController::class, 'history'])->name('settings.history');
        Route::get('admins', fn () => redirect()->route('super.admins.index'));
        Route::prefix('pengelola')->name('admins.')->controller(AdminDirectoryController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{admin}', 'show')->name('show');
            Route::get('{admin}/edit', 'edit')->name('edit');
            Route::put('{admin}', 'update')->name('update');
            Route::delete('{admin}', 'destroy')->name('destroy');
        });
        Route::patch('pengelola/{admin}/verify', [AdminVerificationController::class, 'accept'])->name('admins.verify');
        Route::patch('pengelola/{admin}/reject', [AdminVerificationController::class, 'reject'])->name('admins.reject');
        Route::patch('pengelola/{admin}/deactivate', [AdminVerificationController::class, 'deactivate'])->name('admins.deactivate');
        Route::get('admin-verifications', [AdminVerificationController::class, 'index'])->name('admin-verifications.index');
        Route::post('admin-verifications/{admin}/accept', [AdminVerificationController::class, 'accept'])->name('admin-verifications.accept');
        Route::post('admin-verifications/{admin}/reject', [AdminVerificationController::class, 'reject'])->name('admin-verifications.reject');
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    });
});
