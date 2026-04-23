<?php

namespace App\Services\Home;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomeMediaAssetService
{
    public function resolveItemImageUrl(string $fotoPath, string $defaultFolder): string
    {
        $cleanPath = str_replace('\\', '/', trim($fotoPath, '/'));
        if ($cleanPath === '') {
            return asset('img/login-image.png');
        }

        if (Str::startsWith($cleanPath, ['http://', 'https://'])) {
            return $cleanPath;
        }

        if (Str::startsWith($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        } elseif (Str::startsWith($cleanPath, 'public/')) {
            $cleanPath = substr($cleanPath, 7);
        }

        [$folder, $subPath] = array_pad(explode('/', $cleanPath, 2), 2, '');

        if (in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== '') {
            $relative = $folder . '/' . $subPath;
            $dataUri = $this->buildImageDataUri($relative);
            if ($dataUri !== null) {
                return $dataUri;
            }
            $version = Storage::disk('public')->exists($relative)
                ? (string) @filemtime(Storage::disk('public')->path($relative))
                : null;
            $url = Storage::disk('public')->exists($relative)
                ? asset('storage/' . $relative)
                : route('media.image', ['folder' => $folder, 'path' => $subPath]);

            return $version ? ($url . '?v=' . $version) : $url;
        }

        if ($subPath !== '') {
            $relative = $defaultFolder . '/' . $cleanPath;
            if (Storage::disk('public')->exists($relative)) {
                $dataUri = $this->buildImageDataUri($relative);
                if ($dataUri !== null) {
                    return $dataUri;
                }
                $version = (string) @filemtime(Storage::disk('public')->path($relative));
                $url = asset('storage/' . $relative);
                return $version ? ($url . '?v=' . $version) : $url;
            }

            return asset('img/login-image.png');
        }

        if (Storage::disk('public')->exists($cleanPath)) {
            return asset('storage/' . $cleanPath);
        }

        if (Storage::disk('public')->exists($defaultFolder . '/' . $cleanPath)) {
            $relative = $defaultFolder . '/' . $cleanPath;
            $dataUri = $this->buildImageDataUri($relative);
            if ($dataUri !== null) {
                return $dataUri;
            }
            $version = (string) @filemtime(Storage::disk('public')->path($relative));
            $url = asset('storage/' . $relative);
            return $version ? ($url . '?v=' . $version) : $url;
        }

        return asset('img/login-image.png');
    }

    public function resolveUserAvatarUrl(?string $profilePath): string
    {
        $defaultAvatar = asset('img/profil.jpg');
        $profilePath = trim((string) $profilePath);
        if ($profilePath === '') {
            return $defaultAvatar;
        }

        if (str_starts_with($profilePath, 'http://') || str_starts_with($profilePath, 'https://')) {
            return $profilePath;
        }

        $normalized = str_replace('\\', '/', ltrim($profilePath, '/'));
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        } elseif (str_starts_with($normalized, 'public/')) {
            $normalized = substr($normalized, 7);
        }

        [$folder, $subPath] = array_pad(explode('/', $normalized, 2), 2, '');
        if (in_array($folder, ['profil-admin', 'profil-user', 'barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== '') {
            if (Storage::disk('public')->exists($normalized)) {
                $dataUri = $this->buildImageDataUri($normalized);
                if ($dataUri !== null) {
                    return $dataUri;
                }

                return route('media.image', ['folder' => $folder, 'path' => $subPath]);
            }

            return $defaultAvatar;
        }

        if (Storage::disk('public')->exists($normalized)) {
            $dataUri = $this->buildImageDataUri($normalized);
            if ($dataUri !== null) {
                return $dataUri;
            }

            return asset('storage/' . $normalized);
        }

        return $defaultAvatar;
    }

    private function buildImageDataUri(string $relativePath): ?string
    {
        if (!Storage::disk('public')->exists($relativePath)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($relativePath);
        $mimeType = mime_content_type($absolutePath) ?: 'image/jpeg';
        if (!is_string($mimeType) || !str_starts_with($mimeType, 'image/')) {
            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'webp' => 'image/webp',
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                default => 'image/jpeg',
            };
        }
        $size = @filesize($absolutePath);
        if ($size === false || $size > 2 * 1024 * 1024) {
            return null;
        }

        $binary = @file_get_contents($absolutePath);
        if ($binary === false) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
    }
}
