<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function show(string $folder, string $path): BinaryFileResponse
    {
        $path = trim($path, '/');
        $path = str_replace(['../', '..\\'], '', $path);
        $relativePath = $folder . '/' . $path;

        abort_unless(Storage::disk('public')->exists($relativePath), 404);

        $absolutePath = Storage::disk('public')->path($relativePath);
        $mimeType = Storage::disk('public')->mimeType($relativePath) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
