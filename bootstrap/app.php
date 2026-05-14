<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use App\Support\ManagerPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
   ->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
        'admin.region.barang' => \App\Http\Middleware\EnsureAdminCanAccessBarangRegion::class,
        'super' => \App\Http\Middleware\SuperAdminMiddleware::class,
    ]);

    $middleware->redirectUsersTo(function (Request $request) {
        $connectionName = (string) Config::get('database.default', 'mysql');
        $connection = (array) Config::get('database.connections.' . $connectionName, []);
        $driver = (string) ($connection['driver'] ?? '');
        $host = (string) ($connection['host'] ?? '');
        $port = (int) ($connection['port'] ?? 3306);
        $shouldSkipAuthGuards = false;

        if (in_array($driver, ['mysql', 'mariadb'], true) && in_array($host, ['127.0.0.1', 'localhost'], true) && $port > 0) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 0.25);
            if (is_resource($socket)) {
                stream_set_timeout($socket, 0, 250000);
                $probe = @fread($socket, 1);
                $meta = stream_get_meta_data($socket);
                fclose($socket);

                if ($probe === false || ($probe === '' && (($meta['timed_out'] ?? false) === true))) {
                    $shouldSkipAuthGuards = true;
                }
            } else {
                $shouldSkipAuthGuards = true;
            }
        }

        if ($shouldSkipAuthGuards) {
            return route('home');
        }

        if (Auth::guard(ManagerPortal::guard())->check()) {
            return route(ManagerPortal::dashboardRoute());
        }

        if (Auth::guard('super_admin')->check()) {
            return route('super.dashboard');
        }

        if (Auth::guard('web')->check()) {
            return route('user.dashboard');
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

            $loginRoute = match (true) {
                ManagerPortal::isPortalRequest($request) => ManagerPortal::loginRoute(),
                $request->is('super') || $request->is('super/*') => 'super.login',
                default => 'login',
            };

            return redirect()
                ->route($loginRoute)
                ->with('status', 'Sesi login sudah kedaluwarsa. Silakan coba login kembali.');
        });
    })->create();
