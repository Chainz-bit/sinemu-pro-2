<?php

namespace App\Services\Support;

use App\Support\WorkflowStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyScopeAuditService
{
    /**
     * @return array<string,mixed>
     */
    public function audit(int $sampleLimit = 20): array
    {
        return [
            'tables' => [
                'barangs' => $this->auditBarangs($sampleLimit),
                'laporan_barang_hilangs' => $this->auditLostReports($sampleLimit),
                'klaims' => $this->auditClaims($sampleLimit),
                'pencocokans' => $this->auditMatches($sampleLimit),
                'admins' => $this->auditAdmins($sampleLimit),
                'wilayahs' => [
                    'exists' => Schema::hasTable('wilayahs'),
                    'total' => $this->tableCount('wilayahs'),
                ],
            ],
            'mapping_fields' => [
                'admins.kecamatan',
                'barangs.lokasi_ditemukan',
                'barangs.detail_lokasi_ditemukan',
                'laporan_barang_hilangs.lokasi_hilang',
                'laporan_barang_hilangs.detail_lokasi_hilang',
            ],
            'recommendations' => [
                'Jangan auto-assign admin_id tanpa aturan bisnis eksplisit.',
                'Isi region_id hanya jika mapping wilayah dari field lokasi/kecamatan jelas.',
                'Data aktif tanpa region_id dan tanpa admin_id perlu review manual sebelum diproses pengelola.',
                'Data selesai/rejected/completed tanpa scope cukup diaudit dan dipastikan tidak masuk queue aktif.',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function auditBarangs(int $sampleLimit): array
    {
        if (!Schema::hasTable('barangs')) {
            return ['exists' => false];
        }

        $hasRegion = Schema::hasColumn('barangs', 'region_id');
        $hasAdmin = Schema::hasColumn('barangs', 'admin_id');

        $base = DB::table('barangs');
        $active = $this->activeBarangQuery();

        return [
            'exists' => true,
            'columns' => $this->scopeColumns('barangs', ['id', 'user_id', 'admin_id', 'region_id', 'kategori_id', 'status_laporan', 'status_barang', 'created_at', 'deleted_at']),
            'total' => (clone $base)->count(),
            'region_id_null' => $hasRegion ? $this->whereNullCount($base, 'region_id') : null,
            'admin_id_null' => $hasAdmin ? $this->whereNullCount($base, 'admin_id') : null,
            'admin_and_region_null' => $hasAdmin && $hasRegion
                ? (clone $base)->whereNull('admin_id')->whereNull('region_id')->count()
                : null,
            'active_without_scope' => $hasAdmin && $hasRegion
                ? (clone $active)->whereNull('admin_id')->whereNull('region_id')->count()
                : null,
            'region_null_statuses' => $hasRegion
                ? $this->statusBreakdown((clone $base)->whereNull('region_id'), ['status_laporan', 'status_barang'])
                : [],
            'samples_without_scope' => $hasAdmin && $hasRegion
                ? $this->samples((clone $base)->whereNull('admin_id')->whereNull('region_id'), ['id', 'user_id', 'admin_id', 'region_id', 'status_laporan', 'status_barang', 'created_at'], $sampleLimit)
                : [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function auditLostReports(int $sampleLimit): array
    {
        if (!Schema::hasTable('laporan_barang_hilangs')) {
            return ['exists' => false];
        }

        $hasRegion = Schema::hasColumn('laporan_barang_hilangs', 'region_id');
        $hasVerifiedBy = Schema::hasColumn('laporan_barang_hilangs', 'verified_by_admin_id');
        $base = DB::table('laporan_barang_hilangs');
        $active = $this->activeLostReportQuery();

        return [
            'exists' => true,
            'columns' => $this->scopeColumns('laporan_barang_hilangs', ['id', 'user_id', 'admin_id', 'region_id', 'verified_by_admin_id', 'status_laporan', 'created_at', 'deleted_at']),
            'total' => (clone $base)->count(),
            'region_id_null' => $hasRegion ? $this->whereNullCount($base, 'region_id') : null,
            'verified_by_admin_id_null' => $hasVerifiedBy ? $this->whereNullCount($base, 'verified_by_admin_id') : null,
            'active_region_id_null' => $hasRegion ? (clone $active)->whereNull('region_id')->count() : null,
            'region_null_statuses' => $hasRegion
                ? $this->statusBreakdown((clone $base)->whereNull('region_id'), ['status_laporan'])
                : [],
            'samples_region_id_null' => $hasRegion
                ? $this->samples((clone $base)->whereNull('region_id'), ['id', 'user_id', 'region_id', 'verified_by_admin_id', 'status_laporan', 'created_at'], $sampleLimit)
                : [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function auditClaims(int $sampleLimit): array
    {
        if (!Schema::hasTable('klaims')) {
            return ['exists' => false];
        }

        $hasAdmin = Schema::hasColumn('klaims', 'admin_id');
        $hasRegion = Schema::hasColumn('klaims', 'region_id');
        $base = DB::table('klaims');
        $pending = $this->pendingClaimQuery();
        $pendingWithoutBarangRegion = $this->pendingClaimWithoutBarangRegionQuery();

        return [
            'exists' => true,
            'columns' => $this->scopeColumns('klaims', ['id', 'user_id', 'admin_id', 'barang_id', 'laporan_hilang_id', 'pencocokan_id', 'region_id', 'status_klaim', 'status_verifikasi', 'created_at', 'deleted_at']),
            'total' => (clone $base)->count(),
            'admin_id_null' => $hasAdmin ? $this->whereNullCount($base, 'admin_id') : null,
            'region_id_null' => $hasRegion ? $this->whereNullCount($base, 'region_id') : null,
            'pending' => (clone $pending)->count(),
            'pending_without_barang_region' => (clone $pendingWithoutBarangRegion)->count(),
            'pending_admin_null_without_barang_region' => $hasAdmin
                ? (clone $pendingWithoutBarangRegion)->whereNull('klaims.admin_id')->count()
                : null,
            'admin_null_statuses' => $hasAdmin
                ? $this->statusBreakdown((clone $base)->whereNull('admin_id'), ['status_klaim', 'status_verifikasi'])
                : [],
            'samples_pending_without_barang_region' => $this->samples($pendingWithoutBarangRegion, ['klaims.id', 'klaims.user_id', 'klaims.admin_id', 'klaims.barang_id', 'klaims.status_klaim', 'klaims.status_verifikasi', 'klaims.created_at'], $sampleLimit),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function auditMatches(int $sampleLimit): array
    {
        if (!Schema::hasTable('pencocokans')) {
            return ['exists' => false];
        }

        $hasAdmin = Schema::hasColumn('pencocokans', 'admin_id');
        $base = DB::table('pencocokans');
        $relationWithoutRegion = $this->matchWithoutRelationRegionQuery();
        $activeRelationWithoutRegion = $this->activeMatchWithoutRelationRegionQuery();

        return [
            'exists' => true,
            'columns' => $this->scopeColumns('pencocokans', ['id', 'laporan_hilang_id', 'barang_id', 'admin_id', 'region_id', 'status_pencocokan', 'created_at', 'deleted_at']),
            'total' => (clone $base)->count(),
            'admin_id_null' => $hasAdmin ? $this->whereNullCount($base, 'admin_id') : null,
            'relation_region_missing' => (clone $relationWithoutRegion)->count(),
            'active_relation_region_missing' => (clone $activeRelationWithoutRegion)->count(),
            'admin_null_statuses' => $hasAdmin
                ? $this->statusBreakdown((clone $base)->whereNull('admin_id'), ['status_pencocokan'])
                : [],
            'samples_relation_region_missing' => $this->samples($activeRelationWithoutRegion, ['pencocokans.id', 'pencocokans.laporan_hilang_id', 'pencocokans.barang_id', 'pencocokans.admin_id', 'pencocokans.status_pencocokan', 'pencocokans.created_at'], $sampleLimit),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function auditAdmins(int $sampleLimit): array
    {
        if (!Schema::hasTable('admins')) {
            return ['exists' => false];
        }

        $base = DB::table('admins');
        $hasRegion = Schema::hasColumn('admins', 'region_id');

        return [
            'exists' => true,
            'columns' => $this->scopeColumns('admins', ['id', 'region_id', 'kecamatan', 'status_verifikasi', 'deleted_at']),
            'total' => (clone $base)->count(),
            'region_id_null' => $hasRegion ? $this->whereNullCount($base, 'region_id') : null,
            'active_region_id_null' => $hasRegion
                ? (clone $base)->whereNull('region_id')->where('status_verifikasi', 'active')->whereNull('deleted_at')->count()
                : null,
            'samples_region_id_null' => $hasRegion
                ? $this->samples((clone $base)->whereNull('region_id'), ['id', 'region_id', 'kecamatan', 'status_verifikasi', 'deleted_at'], $sampleLimit)
                : [],
        ];
    }

    private function activeBarangQuery(): Builder
    {
        return DB::table('barangs')
            ->whereIn('status_laporan', [
                WorkflowStatus::REPORT_SUBMITTED,
                WorkflowStatus::REPORT_APPROVED,
                WorkflowStatus::REPORT_MATCHED,
            ])
            ->whereIn('status_barang', [
                WorkflowStatus::FOUND_AVAILABLE,
                WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            ]);
    }

    private function activeLostReportQuery(): Builder
    {
        return DB::table('laporan_barang_hilangs')
            ->whereIn('status_laporan', [
                WorkflowStatus::REPORT_SUBMITTED,
                WorkflowStatus::REPORT_APPROVED,
                WorkflowStatus::REPORT_MATCHED,
            ]);
    }

    private function pendingClaimQuery(): Builder
    {
        return DB::table('klaims')
            ->where(function (Builder $query): void {
                $query->where('status_klaim', WorkflowStatus::CLAIM_LEGACY_PENDING)
                    ->orWhereIn('status_verifikasi', [
                        WorkflowStatus::CLAIM_SUBMITTED,
                        WorkflowStatus::CLAIM_UNDER_REVIEW,
                    ]);
            });
    }

    private function pendingClaimWithoutBarangRegionQuery(): Builder
    {
        return $this->pendingClaimQuery()
            ->leftJoin('barangs', 'klaims.barang_id', '=', 'barangs.id')
            ->where(function (Builder $query): void {
                $query->whereNull('klaims.barang_id')
                    ->orWhereNull('barangs.region_id');
            });
    }

    private function matchWithoutRelationRegionQuery(): Builder
    {
        return DB::table('pencocokans')
            ->leftJoin('barangs', 'pencocokans.barang_id', '=', 'barangs.id')
            ->leftJoin('laporan_barang_hilangs', 'pencocokans.laporan_hilang_id', '=', 'laporan_barang_hilangs.id')
            ->where(function (Builder $query): void {
                $query->whereNull('barangs.region_id')
                    ->orWhereNull('laporan_barang_hilangs.region_id');
            });
    }

    private function activeMatchWithoutRelationRegionQuery(): Builder
    {
        return $this->matchWithoutRelationRegionQuery()
            ->whereIn('pencocokans.status_pencocokan', WorkflowStatus::blockingMatchStatuses());
    }

    private function tableCount(string $table): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->count();
    }

    private function whereNullCount(Builder $query, string $column): int
    {
        return (clone $query)->whereNull($column)->count();
    }

    /**
     * @param array<int,string> $wanted
     * @return array<int,string>
     */
    private function scopeColumns(string $table, array $wanted): array
    {
        $columns = Schema::getColumnListing($table);

        return array_values(array_intersect($wanted, $columns));
    }

    /**
     * @param array<int,string> $columns
     * @return array<int,array<string,mixed>>
     */
    private function statusBreakdown(Builder $query, array $columns): array
    {
        $existingColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($query->from, $column)
        ));

        if ($existingColumns === []) {
            return [];
        }

        return (clone $query)
            ->select(array_merge($existingColumns, [DB::raw('COUNT(*) as total')]))
            ->groupBy($existingColumns)
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * @param array<int,string> $columns
     * @return array<int,array<string,mixed>>
     */
    private function samples(Builder $query, array $columns, int $limit): array
    {
        $safeLimit = max(1, min($limit, 100));

        return (clone $query)
            ->select($columns)
            ->orderBy($columns[0])
            ->limit($safeLimit)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }
}
