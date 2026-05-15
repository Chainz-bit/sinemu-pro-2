<?php

namespace App\Services\Support;

use App\Models\Klaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ClaimEvidenceMigrationService
{
    private const LEGACY_PREFIX = 'verifikasi-klaim/';
    private const PRIVATE_PREFIX = 'private/verifikasi-klaim/';
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * @return array<string,mixed>
     */
    public function migrate(bool $execute = false, ?int $limit = null, ?int $claimId = null, int $sampleLimit = 20): array
    {
        $summary = [
            'mode' => $execute ? 'EXECUTE' : 'DRY RUN',
            'claims_scanned' => 0,
            'claims_with_evidence' => 0,
            'legacy_public_evidence' => 0,
            'already_private' => 0,
            'missing_files' => 0,
            'will_migrate' => 0,
            'skipped' => 0,
            'database_changes' => 0,
            'files_copied' => 0,
            'errors' => 0,
            'samples_will_migrate' => [],
            'warnings' => [],
            'error_samples' => [],
        ];

        if (!Schema::hasTable('klaims') || !Schema::hasColumn('klaims', 'bukti_foto')) {
            $summary['warnings'][] = 'Tabel klaims atau kolom bukti_foto tidak tersedia.';
            return $summary;
        }

        $query = Klaim::query()
            ->whereNotNull('bukti_foto')
            ->where('bukti_foto', '<>', '[]')
            ->orderBy('id');

        if ($claimId !== null) {
            $query->whereKey($claimId);
        } elseif ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        $query->get(['id', 'bukti_foto', 'updated_at'])->each(function (Klaim $claim) use (&$summary, $execute, $sampleLimit): void {
            $summary['claims_scanned']++;
            $paths = $this->claimEvidencePaths($claim);
            if ($paths === []) {
                return;
            }

            $summary['claims_with_evidence']++;
            $updatedPaths = $paths;
            $copiedForClaim = [];
            $claimChanged = false;

            foreach ($paths as $index => $rawPath) {
                $classification = $this->classifyPath($rawPath);
                if ($classification['status'] === 'private') {
                    $summary['already_private']++;
                    continue;
                }

                if ($classification['status'] === 'invalid') {
                    $summary['skipped']++;
                    $this->sample($summary['warnings'], [
                        'claim_id' => $claim->id,
                        'index' => $index,
                        'path' => $rawPath,
                        'reason' => $classification['reason'],
                    ], $sampleLimit);
                    continue;
                }

                $legacyPath = $classification['path'];
                if (!Storage::disk('public')->exists($legacyPath)) {
                    $summary['missing_files']++;
                    $summary['skipped']++;
                    $this->sample($summary['warnings'], [
                        'claim_id' => $claim->id,
                        'index' => $index,
                        'path' => $legacyPath,
                        'reason' => 'missing_public_file',
                    ], $sampleLimit);
                    continue;
                }

                $summary['legacy_public_evidence']++;
                $summary['will_migrate']++;
                $this->sample($summary['samples_will_migrate'], [
                    'claim_id' => $claim->id,
                    'index' => $index,
                    'from' => $legacyPath,
                    'to' => self::PRIVATE_PREFIX . now()->format('Y/m') . '/<random>.' . strtolower(pathinfo($legacyPath, PATHINFO_EXTENSION)),
                ], $sampleLimit);

                if (!$execute) {
                    continue;
                }

                try {
                    $newPath = $this->copyToPrivate($legacyPath);
                    $copiedForClaim[] = $newPath;
                    $updatedPaths[$index] = $newPath;
                    $claimChanged = true;
                    $summary['files_copied']++;
                } catch (Throwable $exception) {
                    $summary['errors']++;
                    $summary['skipped']++;
                    $this->sample($summary['error_samples'], [
                        'claim_id' => $claim->id,
                        'index' => $index,
                        'path' => $legacyPath,
                        'error' => $exception->getMessage(),
                    ], $sampleLimit);
                }
            }

            if (!$execute || !$claimChanged) {
                return;
            }

            try {
                DB::transaction(function () use ($claim, $updatedPaths): void {
                    if (!$claim->forceFill(['bukti_foto' => array_values($updatedPaths)])->save()) {
                        throw new \RuntimeException('Database update returned false.');
                    }
                });
                $summary['database_changes']++;
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($copiedForClaim);
                $summary['errors']++;
                $summary['files_copied'] -= count($copiedForClaim);
                $this->sample($summary['error_samples'], [
                    'claim_id' => $claim->id,
                    'path' => implode(', ', $copiedForClaim),
                    'error' => 'database_update_failed: ' . $exception->getMessage(),
                ], $sampleLimit);
            }
        });

        return $summary;
    }

    /**
     * @return array<int,string>
     */
    private function claimEvidencePaths(Klaim $claim): array
    {
        return array_values(array_filter(
            (array) ($claim->bukti_foto ?? []),
            fn (mixed $path): bool => is_string($path) && trim($path) !== ''
        ));
    }

    /**
     * @return array{status:string,path:string,reason:string|null}
     */
    private function classifyPath(string $rawPath): array
    {
        $trimmed = trim($rawPath);
        if ($trimmed === '') {
            return ['status' => 'invalid', 'path' => $rawPath, 'reason' => 'empty_path'];
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return ['status' => 'invalid', 'path' => $rawPath, 'reason' => 'external_url'];
        }

        if (str_starts_with($trimmed, '/') || str_contains($trimmed, '\\') || str_contains($trimmed, '..')) {
            return ['status' => 'invalid', 'path' => $rawPath, 'reason' => 'unsafe_path'];
        }

        $normalized = str_replace('\\', '/', $trimmed);
        $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return ['status' => 'invalid', 'path' => $normalized, 'reason' => 'unsupported_extension'];
        }

        if (str_starts_with($normalized, self::PRIVATE_PREFIX)) {
            return ['status' => 'private', 'path' => $normalized, 'reason' => null];
        }

        if (!str_starts_with($normalized, self::LEGACY_PREFIX)) {
            return ['status' => 'invalid', 'path' => $normalized, 'reason' => 'outside_legacy_folder'];
        }

        return ['status' => 'legacy', 'path' => $normalized, 'reason' => null];
    }

    private function copyToPrivate(string $legacyPath): string
    {
        $extension = strtolower(pathinfo($legacyPath, PATHINFO_EXTENSION));
        $directory = self::PRIVATE_PREFIX . now()->format('Y/m');
        $newPath = $directory . '/' . Str::uuid()->toString() . '.' . $extension;

        while (Storage::disk('local')->exists($newPath)) {
            $newPath = $directory . '/' . Str::uuid()->toString() . '.' . $extension;
        }

        $stream = Storage::disk('public')->readStream($legacyPath);
        if (!is_resource($stream)) {
            throw new \RuntimeException('Tidak bisa membaca file public legacy.');
        }

        try {
            if (!Storage::disk('local')->writeStream($newPath, $stream)) {
                throw new \RuntimeException('Tidak bisa menulis file private.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $newPath;
    }

    /**
     * @param array<int,mixed> $items
     * @param mixed $item
     */
    private function sample(array &$items, mixed $item, int $limit): void
    {
        if (count($items) < $limit) {
            $items[] = $item;
        }
    }
}
