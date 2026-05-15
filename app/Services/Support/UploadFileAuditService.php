<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class UploadFileAuditService
{
    private const FOLDERS = [
        'barang-hilang',
        'barang-temuan',
        'profil-admin',
        'profil-super',
        'profil-user',
        'verifikasi-klaim',
    ];

    /**
     * @return array<string,mixed>
     */
    public function audit(int $sampleLimit = 20): array
    {
        $references = $this->collectReferences();
        $referencedPaths = array_values(array_unique(array_column($references, 'path')));
        $existingFiles = $this->collectExistingFiles();
        $missingReferences = array_values(array_filter(
            $references,
            fn (array $reference): bool => !Storage::disk('public')->exists($reference['path'])
        ));
        $orphanFiles = array_values(array_diff($existingFiles, $referencedPaths));

        return [
            'folders' => self::FOLDERS,
            'referenced_paths' => count($references),
            'unique_referenced_paths' => count($referencedPaths),
            'existing_files_in_audited_folders' => count($existingFiles),
            'missing_files' => count($missingReferences),
            'orphan_files' => count($orphanFiles),
            'samples_missing_files' => array_slice($missingReferences, 0, $sampleLimit),
            'samples_orphan_files' => array_slice($orphanFiles, 0, $sampleLimit),
            'notes' => [
                'Audit ini hanya membaca database dan storage public.',
                'Jangan hapus orphan otomatis sebelum memastikan file tidak dipakai data legacy di luar kolom yang diaudit.',
                'Bukti klaim di verifikasi-klaim bersifat sensitif; pertimbangkan migrasi bertahap ke private disk dengan controller berotorisasi.',
            ],
        ];
    }

    /**
     * @return array<int,array{table:string,column:string,id:int|string,path:string}>
     */
    private function collectReferences(): array
    {
        $references = [];

        foreach ([
            ['barangs', 'foto_barang'],
            ['laporan_barang_hilangs', 'foto_barang'],
            ['users', 'profil'],
            ['admins', 'profil'],
            ['super_admins', 'profil'],
        ] as [$table, $column]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->select(['id', $column])
                ->whereNotNull($column)
                ->orderBy('id')
                ->lazyById()
                ->each(function (object $row) use (&$references, $table, $column): void {
                    $path = $this->normalizePath((string) $row->{$column});
                    if ($path) {
                        $references[] = [
                            'table' => $table,
                            'column' => $column,
                            'id' => $row->id,
                            'path' => $path,
                        ];
                    }
                });
        }

        if (Schema::hasTable('klaims') && Schema::hasColumn('klaims', 'bukti_foto')) {
            DB::table('klaims')
                ->select(['id', 'bukti_foto'])
                ->whereNotNull('bukti_foto')
                ->orderBy('id')
                ->lazyById()
                ->each(function (object $row) use (&$references): void {
                    $paths = json_decode((string) $row->bukti_foto, true);
                    foreach (is_array($paths) ? $paths : [(string) $row->bukti_foto] as $rawPath) {
                        $path = is_string($rawPath) ? $this->normalizePath($rawPath) : null;
                        if ($path) {
                            $references[] = [
                                'table' => 'klaims',
                                'column' => 'bukti_foto',
                                'id' => $row->id,
                                'path' => $path,
                            ];
                        }
                    }
                });
        }

        return $references;
    }

    /**
     * @return array<int,string>
     */
    private function collectExistingFiles(): array
    {
        $files = [];
        foreach (self::FOLDERS as $folder) {
            $files = array_merge($files, Storage::disk('public')->allFiles($folder));
        }

        return array_values(array_unique($files));
    }

    private function normalizePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return null;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (str_contains($path, '..') || !preg_match('#^(' . implode('|', self::FOLDERS) . ')/#', $path)) {
            return null;
        }

        return $path;
    }
}
