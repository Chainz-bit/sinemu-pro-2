<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserItemActionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->get('/dashboard', [HomeController::class, 'index'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/laporan/hilang', [UserItemActionController::class, 'storeLostReport'])->name('user.lost-reports.store');
    Route::post('/laporan/temuan', [UserItemActionController::class, 'storeFoundReport'])->name('user.found-reports.store');
    Route::post('/klaim', [UserItemActionController::class, 'storeClaim'])->name('user.claims.store');
});
