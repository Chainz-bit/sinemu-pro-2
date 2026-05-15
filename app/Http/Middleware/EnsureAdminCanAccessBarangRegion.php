<?php

namespace App\Http\Middleware;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminCanAccessBarangRegion
{
    /**
     * Pastikan pengelola barang wilayah hanya dapat mengakses barang pada region yang sama.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = \App\Support\ManagerPortal::user();
        abort_if(!$admin, 403);

        $barang = $this->resolveBarangFromRoute($request);
        if ($barang) {
            abort_if(empty($admin->region_id), 403, ucfirst(\App\Support\RoleLabels::managerLower()) . ' belum memiliki wilayah akses.');
            abort_if(empty($barang->region_id), 403, 'Barang belum memiliki wilayah yang dapat diproses.');
            abort_if((int) $barang->region_id !== (int) $admin->region_id, 403, 'Anda tidak memiliki akses ke barang dari wilayah lain.');
        }

        $laporan = $this->resolveLostReportFromRoute($request);
        if ($laporan) {
            abort_if(empty($admin->region_id), 403, ucfirst(\App\Support\RoleLabels::managerLower()) . ' belum memiliki wilayah akses.');
            abort_if(empty($laporan->region_id), 403, 'Laporan belum memiliki wilayah yang dapat diproses.');
            abort_if((int) $laporan->region_id !== (int) $admin->region_id, 403, 'Anda tidak memiliki akses ke laporan dari wilayah lain.');
        }

        return $next($request);
    }

    private function resolveBarangFromRoute(Request $request): ?Barang
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        foreach (['barang', 'item'] as $parameter) {
            $value = $route->parameter($parameter);

            if ($value instanceof Barang) {
                return $value;
            }

            if (is_numeric($value)) {
                return Barang::query()->findOrFail((int) $value);
            }
        }

        return null;
    }

    private function resolveLostReportFromRoute(Request $request): ?LaporanBarangHilang
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        foreach (['laporanBarangHilang', 'laporan'] as $parameter) {
            $value = $route->parameter($parameter);

            if ($value instanceof LaporanBarangHilang) {
                return $value;
            }

            if (is_numeric($value)) {
                return LaporanBarangHilang::query()->findOrFail((int) $value);
            }
        }

        return null;
    }
}
