<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureAdminCanAccessBarangRegion;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Support\ManagerPortal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'pengelola-barang/*',
        ]);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'admin.region.barang' => EnsureAdminCanAccessBarangRegion::class,
            'super' => SuperAdminMiddleware::class,
        ]);

        $middleware->redirectUsersTo(function (Request $request) {
            $connectionName = (string) Config::get('database.default', 'mysql');
            $connection = (array) Config::get('database.connections.'.$connectionName, []);
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
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Tidak punya akses untuk data ini.'], 403);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        });

        $exceptions->render(function (HttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = $exception->getMessage();

            if ($status === 403) {
                $message = $message !== '' ? $message : 'Tidak punya akses untuk data ini.';
            } elseif ($status === 404) {
                $message = $message !== '' ? $message : 'Data tidak ditemukan.';
            } elseif ($status === 409) {
                $message = $message !== '' ? $message : 'Terjadi konflik data.';
            }

            return response()->json(['message' => $message], $status);
        });

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
