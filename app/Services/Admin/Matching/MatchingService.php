<?php

namespace App\Services\Admin\Matching;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Support\WorkflowStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MatchingService
{
    /**
     * @var array<int,string>
     */
    private const REVIEWED_MATCH_STATUSES = [
        WorkflowStatus::MATCH_CONFIRMED,
        WorkflowStatus::MATCH_CLAIM_IN_PROGRESS,
        WorkflowStatus::MATCH_CLAIM_APPROVED,
        WorkflowStatus::MATCH_COMPLETED,
        WorkflowStatus::MATCH_CANCELLED,
    ];

    public function findCandidatesForLostReport(LaporanBarangHilang $laporan, int $limit = 8): Collection
    {
        $isLostReportBlocked = Pencocokan::query()
            ->where('laporan_hilang_id', (int) $laporan->id)
            ->whereIn('status_pencocokan', WorkflowStatus::blockingMatchStatuses())
            ->exists();

        if ($isLostReportBlocked) {
            return collect();
        }

        $query = Barang::query()->with('kategori:id,nama_kategori');

        if (Schema::hasColumn('barangs', 'status_laporan')) {
            $query->where('status_laporan', WorkflowStatus::REPORT_APPROVED);
        }

        $query->whereIn('status_barang', [
            WorkflowStatus::FOUND_AVAILABLE,
            WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
        ]);

        $query->whereDoesntHave('pencocokans', function ($matchQuery) use ($laporan): void {
            $matchQuery
                ->where('laporan_hilang_id', (int) $laporan->id)
                ->whereIn('status_pencocokan', self::REVIEWED_MATCH_STATUSES);
        });

        $query->whereDoesntHave('pencocokans', function ($matchQuery): void {
            $matchQuery->whereIn('status_pencocokan', WorkflowStatus::blockingMatchStatuses());
        });

        return $query
            ->latest('updated_at')
            ->get()
            ->map(function (Barang $barang) use ($laporan) {
                $score = $this->scoreForPair($laporan, $barang);

                return (object) [
                    'barang' => $barang,
                    'score' => $score['score'],
                    'reasons' => $score['reasons'],
                    'meta' => $score['meta'],
                ];
            })
            ->filter(fn ($row) => $row->score >= 35)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    public function findCandidatesForFoundItem(Barang $barang, int $limit = 8): Collection
    {
        $isFoundItemBlocked = Pencocokan::query()
            ->where('barang_id', (int) $barang->id)
            ->whereIn('status_pencocokan', WorkflowStatus::blockingMatchStatuses())
            ->exists();

        if ($isFoundItemBlocked) {
            return collect();
        }

        $query = LaporanBarangHilang::query();

        if (Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
            $query->where('status_laporan', WorkflowStatus::REPORT_APPROVED);
        }

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $query->where('sumber_laporan', 'lapor_hilang');
        }

        $query->whereDoesntHave('pencocokans', function ($matchQuery) use ($barang): void {
            $matchQuery
                ->where('barang_id', (int) $barang->id)
                ->whereIn('status_pencocokan', self::REVIEWED_MATCH_STATUSES);
        });

        $query->whereDoesntHave('pencocokans', function ($matchQuery): void {
            $matchQuery->whereIn('status_pencocokan', WorkflowStatus::blockingMatchStatuses());
        });

        return $query
            ->latest('updated_at')
            ->get()
            ->map(function (LaporanBarangHilang $laporan) use ($barang) {
                $score = $this->scoreForPair($laporan, $barang);

                return (object) [
                    'laporan' => $laporan,
                    'score' => $score['score'],
                    'reasons' => $score['reasons'],
                    'meta' => $score['meta'],
                ];
            })
            ->filter(fn ($row) => $row->score >= 35)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * @return array{score:int,reasons:array<int,string>,meta:array<string,int>}
     */
    public function scoreForPair(LaporanBarangHilang $laporan, Barang $barang): array
    {
        $meta = [];
        $reasons = [];

        $nameScore = (int) round($this->tokenSimilarity((string) $laporan->nama_barang, (string) $barang->nama_barang) * 30);
        $meta['nama_barang'] = $nameScore;
        if ($nameScore >= 15) {
            $reasons[] = 'Nama barang sangat mirip';
        }

        $categoryScore = $this->stringEquals((string) ($laporan->kategori_barang ?? ''), (string) ($barang->kategori?->nama_kategori ?? '')) ? 15 : 0;
        $meta['kategori'] = $categoryScore;
        if ($categoryScore > 0) {
            $reasons[] = 'Kategori sama';
        }

        $colorScore = $this->stringEquals((string) ($laporan->warna_barang ?? ''), (string) ($barang->warna_barang ?? '')) ? 10 : 0;
        $meta['warna'] = $colorScore;
        if ($colorScore > 0) {
            $reasons[] = 'Warna sama';
        }

        $brandScore = $this->stringEquals((string) ($laporan->merek_barang ?? ''), (string) ($barang->merek_barang ?? '')) ? 8 : 0;
        $meta['merek'] = $brandScore;
        if ($brandScore > 0) {
            $reasons[] = 'Merek sama';
        }

        $serialScore = $this->scoreSerial((string) ($laporan->nomor_seri ?? ''), (string) ($barang->nomor_seri ?? ''));
        $meta['nomor_seri'] = $serialScore;
        if ($serialScore >= 40) {
            $reasons[] = 'Nomor seri sama persis';
        } elseif ($serialScore > 0) {
            $reasons[] = 'Nomor seri mirip';
        }

        $locationScore = (int) round($this->tokenSimilarity((string) $laporan->lokasi_hilang, (string) $barang->lokasi_ditemukan) * 15);
        $meta['lokasi'] = $locationScore;
        if ($locationScore >= 8) {
            $reasons[] = 'Lokasi berdekatan';
        }

        $dateScore = $this->scoreDate((string) ($laporan->tanggal_hilang ?? ''), (string) ($barang->tanggal_ditemukan ?? ''));
        $meta['tanggal'] = $dateScore;
        if ($dateScore > 0) {
            $reasons[] = 'Tanggal kejadian berdekatan';
        }

        $descLeft = trim((string) ($laporan->keterangan ?? '').' '.(string) ($laporan->ciri_khusus ?? ''));
        $descRight = trim((string) ($barang->deskripsi ?? '').' '.(string) ($barang->ciri_khusus ?? ''));
        $descriptionScore = (int) round($this->tokenSimilarity($descLeft, $descRight) * 10);
        $meta['deskripsi'] = $descriptionScore;
        if ($descriptionScore >= 5) {
            $reasons[] = 'Deskripsi atau ciri khas mirip';
        }

        $total = array_sum($meta);
        $score = max(0, min(100, $total));

        return [
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
            'meta' => $meta,
        ];
    }

    private function scoreSerial(string $left, string $right): int
    {
        $left = $this->normalizeText($left);
        $right = $this->normalizeText($right);

        if ($left === '' || $right === '') {
            return 0;
        }

        if ($left === $right) {
            return 40;
        }

        return str_contains($left, $right) || str_contains($right, $left) ? 20 : 0;
    }

    private function scoreDate(string $lostDate, string $foundDate): int
    {
        if ($lostDate === '' || $foundDate === '') {
            return 0;
        }

        try {
            $diff = Carbon::parse($lostDate)->diffInDays(Carbon::parse($foundDate));
        } catch (\Throwable) {
            return 0;
        }

        return match (true) {
            $diff <= 1 => 12,
            $diff <= 3 => 8,
            $diff <= 7 => 4,
            default => 0,
        };
    }

    private function tokenSimilarity(string $left, string $right): float
    {
        $leftTokens = $this->tokens($left);
        $rightTokens = $this->tokens($right);

        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $intersect = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique(array_merge($leftTokens, $rightTokens)));

        if ($union === 0) {
            return 0.0;
        }

        return $intersect / $union;
    }

    private function tokens(string $text): array
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            array_unique(explode(' ', $normalized)),
            fn ($token) => mb_strlen((string) $token) >= 2
        ));
    }

    private function stringEquals(string $left, string $right): bool
    {
        $left = $this->normalizeText($left);
        $right = $this->normalizeText($right);

        return $left !== '' && $right !== '' && $left === $right;
    }

    private function normalizeText(string $text): string
    {
        $text = Str::lower(trim($text));
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? '';
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        return trim($text);
    }
}
