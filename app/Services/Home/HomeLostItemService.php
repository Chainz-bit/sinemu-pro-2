<?php

namespace App\Services\Home;

use App\Models\LaporanBarangHilang;
use App\Support\ReportStatusPresenter;
use App\Support\WorkflowStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HomeLostItemService
{
    public function __construct(
        private readonly HomeMediaAssetService $mediaAssetService
    ) {
    }

    /**
     * @return array{0:array<int,array<string,mixed>>,1:int}
     */
    public function build(int $limit, callable $safeDatabaseCall, callable $resolveHomeScopeQuery): array
    {
        return $safeDatabaseCall(function () use ($limit, $resolveHomeScopeQuery) {
            $lostQuery = LaporanBarangHilang::query();
            $lostQuery = $resolveHomeScopeQuery($lostQuery, 'laporan_barang_hilangs');
            $lostTotalCount = (clone $lostQuery)->count();

            $lostItemsQuery = $lostQuery
                ->select([
                    'id',
                    'kategori_barang',
                    'nama_barang',
                    'lokasi_hilang',
                    'tanggal_hilang',
                    'foto_barang',
                    'status_laporan',
                    'updated_at',
                ])
                ->latest('updated_at')
                ->limit($limit);

            $lostItems = $lostItemsQuery
                ->get()
                ->map(function ($item) {
                    $categoryLabel = trim((string) ($item->kategori_barang ?? ''));
                    $reportStatus = ReportStatusPresenter::key((string) ($item->status_laporan ?? WorkflowStatus::REPORT_SUBMITTED));
                    [$lostStatusLabel, $lostStatusClass] = match ($reportStatus) {
                        WorkflowStatus::REPORT_APPROVED => ['Terverifikasi', 'item-status-info'],
                        WorkflowStatus::REPORT_MATCHED => ['Sudah Dicocokkan', 'item-status-success'],
                        WorkflowStatus::REPORT_CLAIMED => ['Sedang Diklaim', 'item-status-warning'],
                        WorkflowStatus::REPORT_COMPLETED => ['Selesai', 'item-status-success'],
                        WorkflowStatus::REPORT_REJECTED => ['Ditolak', 'item-status-muted'],
                        default => ['Menunggu Verifikasi', 'item-status-warning'],
                    };

                    return [
                        'id' => $item->id,
                        'category' => strtoupper($categoryLabel !== '' ? $categoryLabel : 'UMUM'),
                        'name' => $item->nama_barang,
                        'location' => $this->normalizeLocationLabel((string) $item->lokasi_hilang),
                        'date' => $item->tanggal_hilang ? Carbon::parse((string) $item->tanggal_hilang)->format('m/d/Y') : '',
                        'date_label' => $item->tanggal_hilang ? Carbon::parse((string) $item->tanggal_hilang)->translatedFormat('d M Y') : '-',
                        'image_url' => $this->mediaAssetService->resolveItemImageUrl((string) ($item->foto_barang ?? ''), 'barang-hilang'),
                        'detail_url' => route('home.lost-detail', $item->id),
                        'status_label' => $lostStatusLabel,
                        'status_class' => $lostStatusClass,
                    ];
                })
                ->values()
                ->all();

            return [$lostItems, $lostTotalCount];
        }, [[], 0]);
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
}
