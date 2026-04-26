<?php

namespace App\Services\Admin\Matching;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MatchingScoreService
{
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

        $descLeft = trim((string) ($laporan->keterangan ?? '') . ' ' . (string) ($laporan->ciri_khusus ?? ''));
        $descRight = trim((string) ($barang->deskripsi ?? '') . ' ' . (string) ($barang->ciri_khusus ?? ''));
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
