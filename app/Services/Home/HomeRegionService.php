<?php

namespace App\Services\Home;

use App\Models\Wilayah;
use Illuminate\Support\Str;

class HomeRegionService
{
    /**
     * @param array<int,array<string,mixed>> $lostItems
     * @param array<int,array<string,mixed>> $foundItems
     * @return array{0:array<int,string>,1:array<int,array<string,mixed>>}
     */
    public function build(array $lostItems, array $foundItems, bool $hasRegionTable, callable $safeDatabaseCall): array
    {
        $regions = ['Seluruh Wilayah'];
        $mapRegions = [];
        if ($hasRegionTable) {
            [$regions, $mapRegions] = $safeDatabaseCall(function () use ($lostItems, $foundItems) {
                $regions = ['Seluruh Wilayah'];
                $mapRegions = [];

                $wilayahs = Wilayah::query()
                    ->orderBy('nama_wilayah')
                    ->get(['nama_wilayah', 'lat', 'lng']);

                $regionLabels = $wilayahs
                    ->pluck('nama_wilayah')
                    ->map(fn ($label) => trim((string) $label))
                    ->filter(fn ($label) => $label !== '')
                    ->unique(fn ($label) => Str::lower($label))
                    ->values()
                    ->all();

                if (count($regionLabels) > 0) {
                    $regions = array_merge(['Seluruh Wilayah'], $regionLabels);
                }

                $allLocations = collect(array_merge(
                    array_column($lostItems, 'location'),
                    array_column($foundItems, 'location')
                ))->map(fn ($loc) => Str::lower((string) $loc));

                $mapRegions = $wilayahs->map(function ($wilayah) use ($allLocations) {
                    $key = Str::lower(str_replace('kecamatan', '', (string) $wilayah->nama_wilayah));
                    $activePoints = $allLocations->filter(function ($loc) use ($key) {
                        return str_contains($loc, trim($key));
                    })->count();

                    return [
                        'name' => $wilayah->nama_wilayah,
                        'slug' => Str::slug((string) $wilayah->nama_wilayah),
                        'lat' => $wilayah->lat ? (float) $wilayah->lat : null,
                        'lng' => $wilayah->lng ? (float) $wilayah->lng : null,
                        'active_points' => $activePoints,
                    ];
                })->values()->all();

                return [$regions, $mapRegions];
            }, [$regions, []]);
        }

        if (count($regions) === 1) {
            $regionFromItems = collect(array_merge(
                array_column($lostItems, 'location'),
                array_column($foundItems, 'location')
            ))
                ->map(fn ($label) => trim((string) $label))
                ->filter(fn ($label) => $label !== '')
                ->unique(fn ($label) => Str::lower($label))
                ->sortBy(fn ($label) => Str::lower($label))
                ->values()
                ->all();

            if (count($regionFromItems) > 0) {
                $regions = array_merge(['Seluruh Wilayah'], $regionFromItems);
            }
        }

        return [$regions, $mapRegions];
    }
}
