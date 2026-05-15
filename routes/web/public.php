<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ClaimEvidenceController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/laporan/hilang/{laporanBarangHilang}', [HomeController::class, 'showLostDetail'])->name('home.lost-detail');
Route::get('/laporan/temuan/{barang}', [HomeController::class, 'showFoundDetail'])->name('home.found-detail');
Route::get('/klaim/{klaim}/bukti/{index}', [ClaimEvidenceController::class, 'show'])
    ->whereNumber('index')
    ->name('claims.evidence.show');
Route::get('/media/{folder}/{path}', [MediaController::class, 'show'])
    ->whereIn('folder', ['barang-hilang', 'barang-temuan', 'profil-admin', 'profil-super', 'profil-user'])
    ->where('path', '.*')
    ->name('media.image');
