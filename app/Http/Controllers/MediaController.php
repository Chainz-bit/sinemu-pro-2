<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    private const ALLOWED_FOLDERS = [
        'barang-hilang',
        'barang-temuan',
        'profil-admin',
        'profil-super',
        'profil-user',
    ];

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function show(string $folder, string $path): BinaryFileResponse
    {
        abort_unless(in_array($folder, self::ALLOWED_FOLDERS, true), 404);

        $path = trim(str_replace('\\', '/', rawurldecode($path)), '/');
        abort_if($path === '' || str_contains($path, '..'), 404);
        abort_unless(in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true), 404);

        $relativePath = $folder . '/' . $path;

        abort_unless(Storage::disk('public')->exists($relativePath), 404);

        $absolutePath = Storage::disk('public')->path($relativePath);
        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';
        abort_unless(in_array($mimeType, self::ALLOWED_MIME_TYPES, true), 404);

        // Pastikan tidak ada output liar (spasi/BOM) sebelum binary image.
        while (\ob_get_level() > 0) {
            \ob_end_clean();
        }

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
