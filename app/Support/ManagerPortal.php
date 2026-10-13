<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManagerPortal
{
    public static function technicalName(): string
    {
        return (string) config('roles.admin.technical_name', 'admin');
    }

    public static function guard(): string
    {
        return (string) config('roles.admin.guard', self::technicalName());
    }

    public static function middleware(): string
    {
        return (string) config('roles.admin.middleware', self::technicalName());
    }

    public static function regionMiddleware(): string
    {
        return (string) config('roles.admin.region_middleware', self::middleware().'.region.barang');
    }

    public static function guestMiddleware(): string
    {
        return 'guest:'.self::guard();
    }

    public static function check(): bool
    {
        return Auth::guard(self::guard())->check();
    }

    public static function user(): ?Authenticatable
    {
        return Auth::guard(self::guard())->user();
    }

    public static function id(): int|string|null
    {
        return Auth::guard(self::guard())->id();
    }

    public static function logout(): void
    {
        Auth::guard(self::guard())->logout();
    }

    public static function routePrefix(): string
    {
        return (string) config('roles.admin.route_name_prefix', self::technicalName());
    }

    public static function routeName(string $name): string
    {
        return self::routePrefix().'.'.ltrim($name, '.');
    }

    public static function urlPrefix(): string
    {
        return trim((string) config('roles.admin.url_prefix', 'pengelola-barang'), '/');
    }

    public static function legacyUrlPrefix(): string
    {
        return trim((string) config('roles.admin.legacy_url_prefix', self::technicalName()), '/');
    }

    public static function loginRoute(): string
    {
        return self::routeName('login');
    }

    public static function dashboardRoute(): string
    {
        return self::routeName('dashboard');
    }

    public static function isPortalRequest(Request $request): bool
    {
        $urlPrefix = self::urlPrefix();
        $legacyPrefix = self::legacyUrlPrefix();

        return $request->is($urlPrefix) || $request->is($urlPrefix.'/*')
            || $request->is($legacyPrefix) || $request->is($legacyPrefix.'/*');
    }

    public static function legacyRedirectTarget(?string $path = null): string
    {
        return '/'.trim(self::urlPrefix().'/'.ltrim((string) $path, '/'), '/');
    }
}
