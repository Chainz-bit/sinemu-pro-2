<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\ManagerPortal;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if (!ManagerPortal::check()) {
            return redirect()->route(ManagerPortal::loginRoute());
        }

        $admin = ManagerPortal::user();
        $status = (string) ($admin?->status_verifikasi ?? 'pending');

        if ($status !== 'active') {
            ManagerPortal::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = match ($status) {
                'rejected' => 'Akun Anda ditolak atau memerlukan perbaikan data.',
                'inactive' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi super admin.',
                default => 'Akun Anda masih menunggu verifikasi super admin.',
            };

            return redirect()
                ->route(ManagerPortal::loginRoute())
                ->withErrors([
                    'login' => $message,
                ]);
        }

        return $next($request);
    }
}
