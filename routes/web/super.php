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
        ->middleware('throttle:8,1')
        ->name('login.store');

    Route::middleware('super')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('profile', [ProfileController::class, 'index'])->name('profile');
        Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('pengaturan', [SettingsController::class, 'index'])->name('settings');
        Route::put('pengaturan', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('pengaturan/riwayat', [SettingsController::class, 'history'])->name('settings.history');
        Route::get('admins', [AdminDirectoryController::class, 'index'])->name('admins.index');
        Route::get('admin-verifications', [AdminVerificationController::class, 'index'])->name('admin-verifications.index');
        Route::post('admin-verifications/{admin}/accept', [AdminVerificationController::class, 'accept'])->name('admin-verifications.accept');
        Route::post('admin-verifications/{admin}/reject', [AdminVerificationController::class, 'reject'])->name('admin-verifications.reject');
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    });
});
