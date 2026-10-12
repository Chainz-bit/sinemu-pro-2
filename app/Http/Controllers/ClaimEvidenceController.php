<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Klaim;
use App\Support\ManagerPortal;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClaimEvidenceController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function show(Klaim $klaim, int $index): BinaryFileResponse
    {
        $this->authorizeClaimEvidence($klaim);

        $paths = array_values((array) ($klaim->bukti_foto ?? []));
        abort_unless(array_key_exists($index, $paths) && is_string($paths[$index]), 404);

        [$disk, $path] = $this->resolveStoragePath($paths[$index]);

        /** @var FilesystemAdapter $diskStorage */
        $diskStorage = Storage::disk($disk);
        abort_unless($diskStorage->exists($path), 404);

        $absolutePath = $diskStorage->path($path);
        $mimeType = mime_content_type($absolutePath) ?: ($diskStorage->mimeType($path) ?: 'application/octet-stream');
        abort_unless(in_array($mimeType, self::ALLOWED_MIME_TYPES, true), 404);

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeClaimEvidence(Klaim $klaim): void
    {
        if (Auth::guard('super_admin')->check()) {
            return;
        }

        if (Auth::guard('web')->check()) {
            $this->authorizeForUser(Auth::guard('web')->user(), 'view', $klaim);

            return;
        }

        if (Auth::guard(ManagerPortal::guard())->check()) {
            $admin = Auth::guard(ManagerPortal::guard())->user();
            abort_unless($admin instanceof Admin && $admin->isActive(), 403);
            $this->authorizeForUser($admin, 'view', $klaim);

            return;
        }

        abort(403);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveStoragePath(string $rawPath): array
    {
        $path = trim(str_replace('\\', '/', $rawPath), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        } elseif (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        abort_if($path === '' || str_contains($path, '..'), 404);
        abort_unless(in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true), 404);

        if (str_starts_with($path, 'private/verifikasi-klaim/')) {
            return ['local', $path];
        }

        if (str_starts_with($path, 'verifikasi-klaim/')) {
            return ['public', $path];
        }

        abort(404);
    }
}
