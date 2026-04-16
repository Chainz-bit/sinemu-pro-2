        <?php

        use App\Http\Controllers\ProfileController;
        use App\Http\Controllers\User\DashboardController;
        use App\Http\Controllers\User\FoundReportController;
        use App\Http\Controllers\User\LostReportController;
        use App\Http\Controllers\User\RiwayatKlaimController;
        use App\Http\Controllers\User\UserNotificationController;
        use App\Http\Controllers\User\ProfileController as UserProfileController;
        use App\Http\Controllers\UserItemActionController;
        use Illuminate\Support\Facades\Route;

        Route::middleware('auth')->group(function () {
            // BAGIAN: Kompatibilitas URL lama dashboard user.
            Route::get('/dashboard', function () {
                return redirect()->route('user.dashboard');
            })->name('dashboard');

            // BAGIAN: Halaman dashboard dan form pelaporan user.
            Route::prefix('user')->name('user.')->group(function () {
                Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
                Route::get('/profil', [UserProfileController::class, 'index'])->name('profile');
                Route::get('/riwayat-klaim', [RiwayatKlaimController::class, 'index'])->name('claim-history');
                Route::delete('/riwayat-klaim/{klaim}', [RiwayatKlaimController::class, 'destroy'])->name('claim-history.destroy');
                Route::get('/lapor-barang-hilang', [LostReportController::class, 'create'])->name('lost-reports.create');
                Route::get('/lapor-barang-temuan', [FoundReportController::class, 'create'])->name('found-reports.create');
                Route::post('/notifications/read-all', [UserNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
                Route::post('/notifications/{notification}/read', [UserNotificationController::class, 'markAsRead'])->name('notifications.read');
                Route::delete('/notifications/{notification}', [UserNotificationController::class, 'destroy'])->name('notifications.destroy');
                Route::delete('/notifications', [UserNotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
            });

            Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

            Route::post('/laporan/hilang', [UserItemActionController::class, 'storeLostReport'])->name('user.lost-reports.store');
            Route::delete('/laporan/hilang/{laporanBarangHilang}', [UserItemActionController::class, 'destroyLostReport'])->name('user.lost-reports.destroy');
            Route::post('/laporan/temuan', [UserItemActionController::class, 'storeFoundReport'])->name('user.found-reports.store');
            Route::post('/klaim', [UserItemActionController::class, 'storeClaim'])->name('user.claims.store');
        });
