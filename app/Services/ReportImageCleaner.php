<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use Illuminate\Support\Facades\Storage;

class ReportImageCleaner
{
    public static function purgeIfOrphaned(?string $path): void
    {
        $normalized = self::normalizePath($path);
        if ($normalized === null) {
            return;
        }

        $pathVariants = array_values(array_unique([
            $normalized,
            'storage/' . $normalized,
            '/' . $normalized,
            '/storage/' . $normalized,
        ]));

        $stillUsedByFound = Barang::query()
            ->whereIn('foto_barang', $pathVariants)
            ->exists();

        $stillUsedByLost = LaporanBarangHilang::query()
            ->whereIn('foto_barang', $pathVariants)
            ->exists();

        if ($stillUsedByFound || $stillUsedByLost) {
            return;
        }

        Storage::disk('public')->delete($normalized);
    }

    private static function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return null;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (!preg_match('#^(barang-hilang|barang-temuan|verifikasi-klaim)/#', $path)) {
            return null;
        }

        return $path;
    }
}
