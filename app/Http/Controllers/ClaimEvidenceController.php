<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Klaim;
use App\Support\ManagerPortal;
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
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $absolutePath = Storage::disk($disk)->path($path);
        $mimeType = mime_content_type($absolutePath) ?: Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
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
            abort_unless((int) $klaim->user_id === (int) Auth::guard('web')->id(), 403);
            return;
        }

        if (Auth::guard(ManagerPortal::guard())->check()) {
            $admin = Auth::guard(ManagerPortal::guard())->user();
            abort_unless($admin instanceof Admin && $admin->isActive(), 403);

            $adminRegionId = (int) ($admin->region_id ?? 0);
            abort_unless($adminRegionId > 0, 403);

            $klaim->loadMissing(['barang:id,region_id', 'laporanHilang:id,region_id']);
            $hasRegionalScope = (int) ($klaim->barang?->region_id ?? 0) === $adminRegionId
                || (int) ($klaim->laporanHilang?->region_id ?? 0) === $adminRegionId;
            abort_unless($hasRegionalScope, 403);

            if (!is_null($klaim->admin_id)) {
                abort_unless((int) $klaim->admin_id === (int) $admin->id, 403);
                return;
            }

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
