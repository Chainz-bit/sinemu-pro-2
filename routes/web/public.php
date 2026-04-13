<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/media/{folder}/{path}', [MediaController::class, 'show'])
    ->whereIn('folder', ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'])
    ->where('path', '.*')
    ->name('media.image');
