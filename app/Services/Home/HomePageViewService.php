<?php

namespace App\Services\Home;

use App\Services\Support\DatabaseHealthService;
use App\Support\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class HomePageViewService
{
    private const HOME_ITEMS_LIMIT = 24;
    private bool $skipDatabaseCalls = false;
    private bool $databaseFailureReported = false;
    /** @var array<string,bool> */
    private array $tableExistsCache = [];
    /** @var array<string,bool> */
    private array $columnExistsCache = [];

    public function __construct(
        private readonly HomeLostItemService $lostItemService,
        private readonly HomeFoundItemService $foundItemService,
        private readonly HomeCategoryService $categoryService,
        private readonly HomeRegionService $regionService,
        private readonly HomePickupLocationService $pickupLocationService,
        private readonly HomeClaimableLostReportService $claimableLostReportService,
        private readonly DatabaseHealthService $databaseHealthService,
        private readonly HomeMediaAssetService $mediaAssetService,
        private readonly HomeLostDetailService $lostDetailService,
        private readonly HomeFoundDetailService $foundDetailService
    ) {
    }

    public function isDatabaseResponsive(): bool
    {
        return $this->databaseHealthService->isResponsive();
    }

    /**
     * @return array<string,mixed>
     */
    public function buildHomeViewData(mixed $currentUser, bool $includeClaimableReports): array
    {
        [$lostItems, $lostTotalCount] = $this->getLostItems();
        [$foundItems, $foundTotalCount] = $this->getFoundItems();
        [$categories, $kategoriOptions] = $this->getCategories($foundItems);
        [$regions, $mapRegions] = $this->getRegions($lostItems, $foundItems);
        $pickupLocations = $this->getPickupLocations();
        $userName = $this->resolveUserDisplayName($currentUser);
        $userAvatar = $this->mediaAssetService->resolveUserAvatarUrl((string) ($currentUser?->profil ?? ''));
        $userLocation = $currentUser?->location ?? 'Lokasi Anda';
        $claimableLostReports = $includeClaimableReports
            ? $this->getClaimableLostReports((int) ($currentUser?->id ?? 0))
            : collect();

        return compact(
            'lostItems',
            'foundItems',
            'lostTotalCount',
            'foundTotalCount',
            'categories',
            'kategoriOptions',
            'regions',
            'mapRegions',
            'pickupLocations',
            'userName',
            'userAvatar',
            'userLocation',
            'claimableLostReports'
        );
    }

    /**
     * @return array{pageTitle:string,detail:object}
     */
    public function buildLostDetailViewData(\App\Models\LaporanBarangHilang $laporanBarangHilang): array
    {
        return $this->lostDetailService->build($laporanBarangHilang);
    }

    /**
     * @return array{pageTitle:string,detail:object}
     */
    public function buildFoundDetailViewData(\App\Models\Barang $barang): array
    {
        return $this->foundDetailService->build($barang);
    }

    /**
     * @return array{0:array<int,array<string,mixed>>,1:int}
     */
    private function getLostItems(): array
    {
        $lostItems = [];
        $lostTotalCount = 0;
        if ($this->hasDatabaseTable('laporan_barang_hilangs')) {
            [$lostItems, $lostTotalCount] = $this->lostItemService->build(
                limit: self::HOME_ITEMS_LIMIT,
                safeDatabaseCall: $this->safeDatabaseCall(...),
                resolveHomeScopeQuery: $this->resolveHomeScopeQuery(...)
            );
        }

        return [$lostItems, $lostTotalCount];
    }

    /**
     * @return array{0:array<int,array<string,mixed>>,1:int}
     */
    private function getFoundItems(): array
    {
        $foundItems = [];
        $foundTotalCount = 0;
        if ($this->hasDatabaseTable('barangs')) {
            [$foundItems, $foundTotalCount] = $this->foundItemService->build(
                limit: self::HOME_ITEMS_LIMIT,
                safeDatabaseCall: $this->safeDatabaseCall(...),
                resolveHomeScopeQuery: $this->resolveHomeScopeQuery(...)
            );
        }

        return [$foundItems, $foundTotalCount];
    }

    /**
     * @param array<int,array<string,mixed>> $foundItems
     * @return array{0:array<int,string>,1:Collection<int,mixed>}
     */
    private function getCategories(array $foundItems): array
    {
        return $this->categoryService->build(
            foundItems: $foundItems,
            hasCategoryTable: $this->hasDatabaseTable('kategoris'),
            safeDatabaseCall: $this->safeDatabaseCall(...)
        );
    }

    /**
     * @param array<int,array<string,mixed>> $lostItems
     * @param array<int,array<string,mixed>> $foundItems
     * @return array{0:array<int,string>,1:array<int,array<string,mixed>>}
     */
    private function getRegions(array $lostItems, array $foundItems): array
    {
        return $this->regionService->build(
            lostItems: $lostItems,
            foundItems: $foundItems,
            hasRegionTable: $this->hasDatabaseTable('wilayahs'),
            safeDatabaseCall: $this->safeDatabaseCall(...)
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getPickupLocations(): array
    {
        return $this->pickupLocationService->build(
            hasAdminTable: $this->hasDatabaseTable('admins'),
            safeDatabaseCall: $this->safeDatabaseCall(...),
            hasDatabaseColumn: $this->hasDatabaseColumn(...)
        );
    }

    private function getClaimableLostReports(int $userId): Collection
    {
        if ($userId <= 0 || !$this->hasDatabaseTable('laporan_barang_hilangs')) {
            return collect();
        }

        return $this->claimableLostReportService->build(
            userId: $userId,
            safeDatabaseCall: $this->safeDatabaseCall(...),
            hasDatabaseColumn: $this->hasDatabaseColumn(...)
        );
    }

    private function resolveUserDisplayName(mixed $user): string
    {
        if (!$user) {
            return 'Pengguna';
        }

        $username = trim((string) ($user->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        $nama = trim((string) ($user->nama ?? $user->name ?? ''));
        if ($nama !== '') {
            return $nama;
        }

        return 'Pengguna';
    }

    private function normalizeLocationLabel(string $location): string
    {
        $location = trim(preg_replace('/\s+/', ' ', $location) ?? '');
        if ($location === '') {
            return '-';
        }

        $lower = Str::lower($location);

        if (Str::startsWith($lower, 'kec ')) {
            $district = trim(substr($location, 4));
            return 'Kecamatan ' . ucwords(Str::lower($district));
        }

        if (Str::startsWith($lower, 'kecamatan ')) {
            $district = trim(substr($location, 10));
            return 'Kecamatan ' . ucwords(Str::lower($district));
        }

        if (Str::startsWith($lower, 'kel ')) {
            $ward = trim(substr($location, 4));
            return 'Kelurahan ' . ucwords(Str::lower($ward));
        }

        if (Str::startsWith($lower, 'kelurahan ')) {
            $ward = trim(substr($location, 10));
            return 'Kelurahan ' . ucwords(Str::lower($ward));
        }

        return ucwords(Str::lower($location));
    }

    private function resolveHomeScopeQuery(mixed $baseQuery, string $tableName): mixed
    {
        if ($tableName === 'laporan_barang_hilangs' && $this->hasDatabaseColumn('laporan_barang_hilangs', 'status_laporan')) {
            $baseQuery->whereIn('status_laporan', [
                WorkflowStatus::REPORT_APPROVED,
                WorkflowStatus::REPORT_MATCHED,
                WorkflowStatus::REPORT_CLAIMED,
            ]);
        }

        if ($tableName === 'barangs' && $this->hasDatabaseColumn('barangs', 'status_laporan')) {
            $baseQuery->whereIn('status_laporan', [
                WorkflowStatus::REPORT_APPROVED,
                WorkflowStatus::REPORT_MATCHED,
                WorkflowStatus::REPORT_CLAIMED,
            ]);
        }

        if ($tableName === 'barangs' && $this->hasDatabaseColumn('barangs', 'status_barang')) {
            $baseQuery->where('status_barang', '!=', 'sudah_dikembalikan');
        }

        return $baseQuery;
    }

    private function hasDatabaseTable(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $exists = $this->safeDatabaseCall(fn () => Schema::hasTable($table), false);
        $this->tableExistsCache[$table] = (bool) $exists;

        return $this->tableExistsCache[$table];
    }

    private function hasDatabaseColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }

        $exists = $this->safeDatabaseCall(fn () => Schema::hasColumn($table, $column), false);
        $this->columnExistsCache[$key] = (bool) $exists;

        return $this->columnExistsCache[$key];
    }

    private function safeDatabaseCall(callable $callback, mixed $fallback): mixed
    {
        if ($this->skipDatabaseCalls) {
            return $fallback;
        }

        if (!$this->databaseHealthService->isResponsive()) {
            $this->skipDatabaseCalls = true;
            return $fallback;
        }

        try {
            return $callback();
        } catch (Throwable $exception) {
            $this->skipDatabaseCalls = true;

            if (!$this->databaseFailureReported) {
                report($exception);
                $this->databaseFailureReported = true;
            }

            return $fallback;
        }
    }
}
