<?php

namespace App\Support;

final class IndramayuDistricts
{
    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return [
            'Anjatan',
            'Arahan',
            'Balongan',
            'Bangodua',
            'Bongas',
            'Cantigi',
            'Cikedung',
            'Gabuswetan',
            'Gantar',
            'Haurgeulis',
            'Indramayu',
            'Jatibarang',
            'Juntinyuat',
            'Kandanghaur',
            'Karangampel',
            'Kedokan Bunder',
            'Kertasemaya',
            'Krangkeng',
            'Kroya',
            'Lelea',
            'Lohbener',
            'Losarang',
            'Pasekan',
            'Patrol',
            'Sindang',
            'Sliyeg',
            'Sukagumiwang',
            'Sukra',
            'Terisi',
            'Tukdana',
            'Widasari',
        ];
    }

    /**
     * @return array<int, array{nama_wilayah: string, lat: float, lng: float}>
     */
    public static function wilayahItems(): array
    {
        return array_map(
            static fn (string $district): array => self::wilayahItem($district),
            self::names()
        );
    }

    /**
     * Koordinat default bersifat representatif dan dapat disesuaikan lagi
     * ke titik kantor kecamatan atau titik pengambilan resmi.
     *
     * @return array{nama_wilayah: string, lat: float, lng: float}
     */
    public static function wilayahItem(string $district): array
    {
        $coordinates = self::coordinatesByDistrict()[self::districtName($district)] ?? [
            'lat' => -6.3264,
            'lng' => 108.3227,
        ];

        return [
            'nama_wilayah' => self::wilayahName($district),
            'lat' => $coordinates['lat'],
            'lng' => $coordinates['lng'],
        ];
    }

    public static function wilayahName(string $district): string
    {
        $district = self::normalizeName($district);

        return str_starts_with(strtolower($district), 'kecamatan ')
            ? $district
            : 'Kecamatan ' . $district;
    }

    public static function normalizeName(string $district): string
    {
        $district = trim(preg_replace('/\s+/', ' ', $district) ?? '');
        $normalized = strtolower(str_replace(['-', '_'], ' ', $district));

        return match ($normalized) {
            'indramayu kota', 'kota indramayu' => 'Indramayu',
            'lobener' => 'Lohbener',
            'kedokanbunder', 'kedokan bunder' => 'Kedokan Bunder',
            default => ucwords($normalized),
        };
    }

    private static function districtName(string $district): string
    {
        $district = self::normalizeName($district);

        return str_starts_with(strtolower($district), 'kecamatan ')
            ? trim(substr($district, 10))
            : $district;
    }

    /**
     * @return array<string, array{lat: float, lng: float}>
     */
    private static function coordinatesByDistrict(): array
    {
        return [
            'Anjatan' => ['lat' => -6.3367, 'lng' => 107.9769],
            'Arahan' => ['lat' => -6.3820, 'lng' => 108.2510],
            'Balongan' => ['lat' => -6.3426, 'lng' => 108.3798],
            'Bangodua' => ['lat' => -6.4806, 'lng' => 108.2366],
            'Bongas' => ['lat' => -6.3660, 'lng' => 108.0462],
            'Cantigi' => ['lat' => -6.2905, 'lng' => 108.2237],
            'Cikedung' => ['lat' => -6.4338, 'lng' => 108.1709],
            'Gabuswetan' => ['lat' => -6.4058, 'lng' => 108.1017],
            'Gantar' => ['lat' => -6.5264, 'lng' => 107.9976],
            'Haurgeulis' => ['lat' => -6.4721, 'lng' => 107.9456],
            'Indramayu' => ['lat' => -6.3264, 'lng' => 108.3227],
            'Jatibarang' => ['lat' => -6.4749, 'lng' => 108.3127],
            'Juntinyuat' => ['lat' => -6.4124, 'lng' => 108.4314],
            'Kandanghaur' => ['lat' => -6.3068, 'lng' => 108.0878],
            'Karangampel' => ['lat' => -6.4576, 'lng' => 108.4518],
            'Kedokan Bunder' => ['lat' => -6.4504, 'lng' => 108.3886],
            'Kertasemaya' => ['lat' => -6.5310, 'lng' => 108.3555],
            'Krangkeng' => ['lat' => -6.5095, 'lng' => 108.5097],
            'Kroya' => ['lat' => -6.4974, 'lng' => 108.0798],
            'Lelea' => ['lat' => -6.4354, 'lng' => 108.2784],
            'Lohbener' => ['lat' => -6.3658, 'lng' => 108.2471],
            'Losarang' => ['lat' => -6.3601, 'lng' => 108.1648],
            'Pasekan' => ['lat' => -6.2644, 'lng' => 108.3280],
            'Patrol' => ['lat' => -6.3140, 'lng' => 108.0241],
            'Sindang' => ['lat' => -6.3220, 'lng' => 108.3240],
            'Sliyeg' => ['lat' => -6.4714, 'lng' => 108.4380],
            'Sukagumiwang' => ['lat' => -6.5752, 'lng' => 108.3567],
            'Sukra' => ['lat' => -6.3048, 'lng' => 107.9873],
            'Terisi' => ['lat' => -6.5653, 'lng' => 108.1528],
            'Tukdana' => ['lat' => -6.5209, 'lng' => 108.2930],
            'Widasari' => ['lat' => -6.5084, 'lng' => 108.2785],
        ];
    }
}
