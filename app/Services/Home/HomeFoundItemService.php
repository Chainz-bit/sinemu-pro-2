<?php

namespace App\Services\Home;

use App\Models\Barang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HomeFoundItemService
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
            $foundQuery = Barang::query()
                ->with('kategori:id,nama_kategori');
            $foundQuery = $resolveHomeScopeQuery($foundQuery, 'barangs');
            $foundTotalCount = (clone $foundQuery)->count();

            $foundItemsQuery = $foundQuery
                ->select([
                    'id',
                    'kategori_id',
                    'nama_barang',
                    'lokasi_ditemukan',
                    'tanggal_ditemukan',
                    'status_barang',
                    'foto_barang',
                    'updated_at',
                ])
                ->latest('updated_at')
                ->limit($limit);

            $foundItems = $foundItemsQuery
                ->get()
                ->map(function ($item) {
                    $statusBarang = (string) ($item->status_barang ?? '');
                    $claimStatusKey = match ($statusBarang) {
                        'dalam_proses_klaim' => 'in_progress',
                        'sudah_diklaim' => 'claimed',
                        'sudah_dikembalikan' => 'returned',
                        default => 'available',
                    };

                    return [
                        'id' => $item->id,
                        'category' => strtoupper($item->kategori->nama_kategori ?? 'UMUM'),
                        'name' => $item->nama_barang,
                        'location' => $this->normalizeLocationLabel((string) $item->lokasi_ditemukan),
                        'date' => $item->tanggal_ditemukan ? Carbon::parse((string) $item->tanggal_ditemukan)->format('m/d/Y') : '',
                        'date_label' => $item->tanggal_ditemukan ? Carbon::parse((string) $item->tanggal_ditemukan)->translatedFormat('d M Y') : '-',
                        'image_url' => $this->mediaAssetService->resolveItemImageUrl((string) ($item->foto_barang ?? ''), 'barang-temuan'),
                        'detail_url' => route('home.found-detail', $item->id),
                        'claim_status_key' => $claimStatusKey,
                        'claim_status_label' => match ($claimStatusKey) {
                            'in_progress' => 'Sedang Diproses Klaim',
                            'claimed' => 'Sudah Diklaim',
                            'returned' => 'Sudah Dikembalikan',
                            default => 'Tersedia untuk Diklaim',
                        },
                        'is_claimable' => $claimStatusKey === 'available',
                    ];
                })
                ->values()
                ->all();

            return [$foundItems, $foundTotalCount];
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
