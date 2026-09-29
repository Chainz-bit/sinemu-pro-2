<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KlaimController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\LaporanHilangController;
use App\Http\Controllers\Api\LaporanTemuanController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SupportDataController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::post('/login/google', [AuthController::class, 'loginWithGoogle'])
    ->middleware('throttle:5,1');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/kategoris', [SupportDataController::class, 'kategoris']);
    Route::get('/wilayahs', [SupportDataController::class, 'wilayahs']);

    Route::get('/laporan', [LaporanController::class, 'index']);
    Route::get('/laporan/publik', [LaporanController::class, 'publik']);

    Route::post('/laporan/hilang', [LaporanHilangController::class, 'store']);
    Route::get('/laporan/hilang/{laporanBarangHilang}', [LaporanHilangController::class, 'show'])
        ->whereNumber('laporanBarangHilang');
    Route::put('/laporan/hilang/{laporanBarangHilang}', [LaporanHilangController::class, 'update'])
        ->whereNumber('laporanBarangHilang');
    Route::post('/laporan/hilang/{laporanBarangHilang}', [LaporanHilangController::class, 'update'])
        ->whereNumber('laporanBarangHilang');
    Route::delete('/laporan/hilang/{laporanBarangHilang}', [LaporanHilangController::class, 'destroy'])
        ->whereNumber('laporanBarangHilang');

    Route::post('/laporan/temuan', [LaporanTemuanController::class, 'store']);
    Route::get('/laporan/temuan/{barang}', [LaporanTemuanController::class, 'show'])
        ->whereNumber('barang');

    Route::post('/barang-temuan/{barang}/klaim', [KlaimController::class, 'store'])
        ->whereNumber('barang');
    Route::get('/klaim', [KlaimController::class, 'index']);
    Route::get('/klaim/{klaim}', [KlaimController::class, 'show'])
        ->whereNumber('klaim');

    Route::get('/notifikasi', [NotifikasiController::class, 'index']);
    Route::patch('/notifikasi/{notification}/read', [NotifikasiController::class, 'markAsRead'])
        ->whereNumber('notification');
});

// Chatbot API (public, rate-limited)
Route::prefix('chatbot')->middleware('throttle:30,1')->group(function () {
    Route::post('/send', [\App\Http\Controllers\Api\ChatbotController::class, 'send']);
    Route::get('/history', [\App\Http\Controllers\Api\ChatbotController::class, 'history']);
    Route::post('/new', [\App\Http\Controllers\Api\ChatbotController::class, 'newConversation']);
});

