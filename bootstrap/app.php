<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
   ->withMiddleware(function (Middleware $middleware) {
    // Izinkan submit form login tanpa validasi CSRF untuk menghindari 419 pada browser tertentu.
    $middleware->validateCsrfTokens(except: [
        'login',
        'logout',
        'register',
        'confirm-password',
        'forgot-password',
        'reset-password',
        'profile',
    ]);

    $middleware->alias([
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
        'super' => \App\Http\Middleware\SuperAdminMiddleware::class,
    ]);

    $middleware->redirectUsersTo(function (Request $request) {
        if (Auth::guard('admin')->check()) {
            return route('admin.dashboard');
        }

        return route('home');
    });
})
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tangani CSRF mismatch agar user tidak terjebak di halaman 419.
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi Anda sudah berakhir. Silakan muat ulang halaman lalu coba lagi.',
                ], 419);
            }

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()
                ->route('login')
                ->with('status', 'Sesi login sudah kedaluwarsa. Silakan coba login kembali.');
        });
    })->create();
