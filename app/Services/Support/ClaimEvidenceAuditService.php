<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ClaimEvidenceAuditService
{
    /**
     * @return array<string,mixed>
     */
    public function audit(int $sampleLimit = 20): array
    {
        $references = $this->collectReferences();
        $legacyPublic = array_values(array_filter($references, fn (array $item): bool => $item['disk'] === 'public'));
        $private = array_values(array_filter($references, fn (array $item): bool => $item['disk'] === 'local'));
        $unknown = array_values(array_filter($references, fn (array $item): bool => $item['disk'] === null));
        $missing = array_values(array_filter($references, function (array $item): bool {
            return is_string($item['disk']) && !Storage::disk($item['disk'])->exists($item['path']);
        }));

        return [
            'claims_with_evidence' => $this->claimsWithEvidenceCount(),
            'evidence_paths' => count($references),
            'legacy_public_paths' => count($legacyPublic),
            'private_paths' => count($private),
            'unknown_paths' => count($unknown),
            'missing_files' => count($missing),
            'samples_legacy_public' => array_slice($legacyPublic, 0, $sampleLimit),
            'samples_private' => array_slice($private, 0, $sampleLimit),
            'samples_unknown' => array_slice($unknown, 0, $sampleLimit),
            'samples_missing' => array_slice($missing, 0, $sampleLimit),
            'notes' => [
                'Audit ini read-only dan tidak meng-copy, memindahkan, atau menghapus file.',
                'Path lama verifikasi-klaim/* tetap dibaca dari disk public melalui route berotorisasi selama masa transisi.',
                'Path baru private/verifikasi-klaim/* dibaca dari disk local melalui route berotorisasi.',
            ],
        ];
    }

    /**
     * @return array<int,array{claim_id:int|string,index:int,path:string,disk:?string}>
     */
    private function collectReferences(): array
    {
        if (!Schema::hasTable('klaims') || !Schema::hasColumn('klaims', 'bukti_foto')) {
            return [];
        }

        $references = [];
        DB::table('klaims')
            ->select(['id', 'bukti_foto'])
            ->whereNotNull('bukti_foto')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row) use (&$references): void {
                $paths = json_decode((string) $row->bukti_foto, true);
                foreach (is_array($paths) ? $paths : [(string) $row->bukti_foto] as $index => $rawPath) {
                    if (!is_string($rawPath)) {
                        continue;
                    }

                    $path = $this->normalizePath($rawPath);
                    if ($path === null) {
                        continue;
                    }

                    $references[] = [
                        'claim_id' => $row->id,
                        'index' => (int) $index,
                        'path' => $path,
                        'disk' => $this->diskForPath($path),
                    ];
                }
            });

        return $references;
    }

    private function claimsWithEvidenceCount(): int
    {
        if (!Schema::hasTable('klaims') || !Schema::hasColumn('klaims', 'bukti_foto')) {
            return 0;
        }

        return DB::table('klaims')
            ->whereNotNull('bukti_foto')
            ->where('bukti_foto', '<>', '[]')
            ->count();
    }

    private function normalizePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_contains($path, '..')) {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        } elseif (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        return $path;
    }

    private function diskForPath(string $path): ?string
    {
        if (str_starts_with($path, 'private/verifikasi-klaim/')) {
            return 'local';
        }

        if (str_starts_with($path, 'verifikasi-klaim/')) {
            return 'public';
        }

        return null;
    }
}
