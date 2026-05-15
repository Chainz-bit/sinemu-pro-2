<?php

namespace App\Services\User\Claims;

use App\Models\Klaim;
use Illuminate\Support\Facades\Storage;

class ClaimProofStorageService
{
    public function deleteProofs(Klaim $klaim): void
    {
        foreach ((array) ($klaim->bukti_foto ?? []) as $path) {
            if (is_string($path) && trim($path) !== '') {
                $normalized = trim(str_replace('\\', '/', $path), '/');
                if (str_starts_with($normalized, 'storage/')) {
                    $normalized = substr($normalized, strlen('storage/'));
                } elseif (str_starts_with($normalized, 'public/')) {
                    $normalized = substr($normalized, strlen('public/'));
                }
                if (str_starts_with($normalized, 'private/verifikasi-klaim/')) {
                    Storage::disk('local')->delete($normalized);
                } elseif (str_starts_with($normalized, 'verifikasi-klaim/')) {
                    Storage::disk('public')->delete($normalized);
                }
            }
        }
    }
}
