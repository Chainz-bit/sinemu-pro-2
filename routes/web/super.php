<?php

use App\Http\Controllers\Super\AdminVerificationController;
use App\Http\Controllers\Super\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('super')->name('super.')->group(function () {
    Route::middleware('guest:super_admin')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('super')->group(function () {
        Route::get('admin-verifications', [AdminVerificationController::class, 'index'])->name('admin-verifications.index');
        Route::post('admin-verifications/{admin}/accept', [AdminVerificationController::class, 'accept'])->name('admin-verifications.accept');
        Route::post('admin-verifications/{admin}/reject', [AdminVerificationController::class, 'reject'])->name('admin-verifications.reject');
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    });
});
