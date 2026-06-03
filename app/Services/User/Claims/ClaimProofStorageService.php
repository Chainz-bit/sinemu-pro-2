<?php

namespace App\Services\User\Claims;

use App\Models\Klaim;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ClaimProofStorageService
{
    /**
     * @return array<int,array{disk:string,path:string}>
     */
    public function collectProofs(Klaim $klaim): array
    {
        $proofs = [];

        foreach ((array) ($klaim->bukti_foto ?? []) as $path) {
            if (is_string($path) && trim($path) !== '') {
                $normalized = trim(str_replace('\\', '/', $path), '/');
                if (str_starts_with($normalized, 'storage/')) {
                    $normalized = substr($normalized, strlen('storage/'));
                } elseif (str_starts_with($normalized, 'public/')) {
                    $normalized = substr($normalized, strlen('public/'));
                }
                if (str_starts_with($normalized, 'private/verifikasi-klaim/')) {
                    $proofs[] = ['disk' => 'local', 'path' => $normalized];
                } elseif (str_starts_with($normalized, 'verifikasi-klaim/')) {
                    $proofs[] = ['disk' => 'public', 'path' => $normalized];
                }
            }
        }

        return $proofs;
    }

    /**
     * @param array<int,array{disk:string,path:string}> $proofs
     */
    public function deleteProofs(array $proofs): void
    {
        foreach ($proofs as $proof) {
            try {
                if (!Storage::disk($proof['disk'])->delete($proof['path'])) {
                    Log::error('Evidence klaim gagal dihapus setelah record klaim dihapus.', $proof);
                }
            } catch (Throwable $exception) {
                Log::error('Evidence klaim gagal dihapus setelah record klaim dihapus.', [
                    ...$proof,
                    'exception' => $exception,
                ]);
            }
        }
    }
}
