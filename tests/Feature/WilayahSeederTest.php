<?php

namespace Tests\Feature;

use App\Models\Wilayah;
use App\Support\IndramayuDistricts;
use Database\Seeders\WilayahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WilayahSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_indramayu_districts_have_wilayah_items_with_coordinates(): void
    {
        $items = collect(IndramayuDistricts::wilayahItems());

        $this->assertCount(count(IndramayuDistricts::names()), $items);
        $this->assertEqualsCanonicalizing(
            array_map(fn (string $district): string => IndramayuDistricts::wilayahName($district), IndramayuDistricts::names()),
            $items->pluck('nama_wilayah')->all()
        );

        $items->each(function (array $item): void {
            $this->assertIsFloat($item['lat']);
            $this->assertIsFloat($item['lng']);
            $this->assertGreaterThanOrEqual(-7.0, $item['lat']);
            $this->assertLessThanOrEqual(-6.0, $item['lat']);
            $this->assertGreaterThanOrEqual(107.0, $item['lng']);
            $this->assertLessThanOrEqual(109.0, $item['lng']);
        });
    }

    public function test_wilayah_seeder_fills_coordinates_for_existing_wilayah(): void
    {
        $wilayah = Wilayah::query()->create([
            'nama_wilayah' => IndramayuDistricts::wilayahName('Lohbener'),
            'lat' => null,
            'lng' => null,
        ]);

        $this->seed(WilayahSeeder::class);

        $expected = IndramayuDistricts::wilayahItem('Lohbener');
        $wilayah->refresh();

        $this->assertSame($expected['lat'], (float) $wilayah->lat);
        $this->assertSame($expected['lng'], (float) $wilayah->lng);
    }

    public function test_wilayah_seeder_is_idempotent_without_duplicates(): void
    {
        $this->seed(WilayahSeeder::class);
        $firstCount = Wilayah::query()->count();

        $this->seed(WilayahSeeder::class);
        $secondCount = Wilayah::query()->count();

        $this->assertSame(count(IndramayuDistricts::names()), $firstCount);
        $this->assertSame($firstCount, $secondCount);
        $this->assertSame(
            $secondCount,
            Wilayah::query()->distinct()->count('nama_wilayah')
        );
    }
}
