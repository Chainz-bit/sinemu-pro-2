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
                Storage::disk('public')->delete($path);
            }
        }
    }
}
