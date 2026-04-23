<?php

namespace App\Services\Home;

use App\Models\Kategori;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HomeCategoryService
{
    /**
     * @param array<int,array<string,mixed>> $foundItems
     * @return array{0:array<int,string>,1:Collection<int,mixed>}
     */
    public function build(array $foundItems, bool $hasCategoryTable, callable $safeDatabaseCall): array
    {
        $categories = ['Semua Kategori'];
        $kategoriOptions = collect();
        if ($hasCategoryTable) {
            $kategoriOptions = $safeDatabaseCall(
                fn () => Kategori::query()->forForm()->get(['id', 'nama_kategori']),
                collect()
            );
        }

        $categoryLabels = $kategoriOptions
            ->pluck('nama_kategori')
            ->merge(
                collect($foundItems)
                    ->pluck('category')
                    ->map(fn ($label) => ucwords(strtolower((string) $label)))
            )
            ->map(fn ($label) => trim((string) $label))
            ->filter(fn ($label) => $label !== '')
            ->reject(fn ($label) => Str::lower($label) === 'tas')
            ->unique(fn ($label) => Str::lower($label))
            ->sortBy(function ($label) {
                $lower = Str::lower($label);
                return $lower === 'lainnya' ? 'zzzzzz' : $lower;
            })
            ->values()
            ->all();

        if (count($categoryLabels) > 0) {
            $categories = array_merge(['Semua Kategori'], $categoryLabels);
        }

        return [$categories, $kategoriOptions];
    }
}
