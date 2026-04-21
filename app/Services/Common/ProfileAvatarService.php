<?php

namespace App\Services\Common;

use Illuminate\Support\Facades\Storage;

class ProfileAvatarService
{
    public function resolve(?string $profilePath, string $defaultAvatar = 'img/profil.jpg'): string
    {
        $defaultAvatarUrl = asset($defaultAvatar);
        $profilePath = trim((string) $profilePath);
        if ($profilePath === '') {
            return $defaultAvatarUrl;
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
                $absolutePath = Storage::disk('public')->path($normalized);
                $mimeType = mime_content_type($absolutePath) ?: 'image/jpeg';
                $binary = @file_get_contents($absolutePath);
                if ($binary !== false) {
                    return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
                }

                return route('media.image', ['folder' => $folder, 'path' => $subPath]);
            }

            return $defaultAvatarUrl;
        }

        if (Storage::disk('public')->exists($normalized)) {
            $absolutePath = Storage::disk('public')->path($normalized);
            $mimeType = mime_content_type($absolutePath) ?: 'image/jpeg';
            $binary = @file_get_contents($absolutePath);
            if ($binary !== false) {
                return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
            }

            return asset('storage/' . $normalized);
        }

        return $defaultAvatarUrl;
    }
}
