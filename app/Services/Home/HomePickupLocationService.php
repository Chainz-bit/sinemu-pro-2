<?php

namespace App\Services\Home;

use App\Models\Admin;
use App\Support\RoleLabels;

class HomePickupLocationService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function build(bool $hasAdminTable, callable $safeDatabaseCall, callable $hasDatabaseColumn): array
    {
        $pickupLocations = [];
        if ($hasAdminTable) {
            $pickupLocations = $safeDatabaseCall(function () use ($hasDatabaseColumn) {
                $hasStatusVerifikasi = $hasDatabaseColumn('admins', 'status_verifikasi');
                $hasRegionId = $hasDatabaseColumn('admins', 'region_id');
                $hasKecamatan = $hasDatabaseColumn('admins', 'kecamatan');
                $hasAlamatLengkap = $hasDatabaseColumn('admins', 'alamat_lengkap');
                $hasPickupAddress = $hasDatabaseColumn('admins', 'pickup_address');
                $hasPickupLat = $hasDatabaseColumn('admins', 'pickup_lat');
                $hasPickupLng = $hasDatabaseColumn('admins', 'pickup_lng');

                if (! $hasStatusVerifikasi || ! $hasRegionId) {
                    return [];
                }

                $selectColumns = ['id', 'instansi', 'region_id'];
                if ($hasKecamatan) {
                    $selectColumns[] = 'kecamatan';
                }
                if ($hasAlamatLengkap) {
                    $selectColumns[] = 'alamat_lengkap';
                }
                if ($hasPickupAddress) {
                    $selectColumns[] = 'pickup_address';
                }
                if ($hasPickupLat) {
                    $selectColumns[] = 'pickup_lat';
                }
                if ($hasPickupLng) {
                    $selectColumns[] = 'pickup_lng';
                }

                $pickupQuery = Admin::query()
                    ->with(['region:id,nama_wilayah,lat,lng'])
                    ->where('status_verifikasi', Admin::STATUS_ACTIVE)
                    ->whereNotNull('region_id')
                    ->whereHas('region')
                    ->orderBy('instansi');

                return $pickupQuery
                    ->get($selectColumns)
                    ->map(function (Admin $admin) use ($hasKecamatan, $hasAlamatLengkap, $hasPickupAddress, $hasPickupLat, $hasPickupLng) {
                        $coordinates = null;
                        if ($hasPickupLat && $hasPickupLng) {
                            $coordinates = $this->resolveCoordinatePair($admin->pickup_lat, $admin->pickup_lng);
                        }

                        $coordinates ??= $this->resolveCoordinatePair($admin->region?->lat, $admin->region?->lng);
                        if ($coordinates === null) {
                            return null;
                        }

                        $instansi = trim((string) ($admin->instansi ?? ''));
                        $kecamatan = $hasKecamatan ? trim((string) ($admin->kecamatan ?? '')) : '';
                        $regionName = trim((string) ($admin->region?->nama_wilayah ?? ''));
                        $pickupAddress = $hasPickupAddress ? trim((string) ($admin->pickup_address ?? '')) : '';
                        $alamatLengkap = $hasAlamatLengkap ? trim((string) ($admin->alamat_lengkap ?? '')) : '';
                        $address = $pickupAddress !== ''
                            ? $pickupAddress
                            : ($alamatLengkap !== ''
                            ? $alamatLengkap
                            : ($regionName !== '' ? $regionName : ($kecamatan !== '' ? ('Kecamatan '.$kecamatan) : ($instansi !== '' ? $instansi : 'Alamat belum tersedia'))));

                        return [
                            'id' => $admin->id,
                            'name' => $instansi !== '' ? $instansi : RoleLabels::manager().' SiNemu',
                            'manager_label' => RoleLabels::manager(),
                            'address' => $address,
                            'kecamatan' => $kecamatan,
                            'lat' => $coordinates['lat'],
                            'lng' => $coordinates['lng'],
                            'phone' => '0851-7438-6642',
                            'hours' => '08.00-20.00 WIB',
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            }, []);
        }

        return $pickupLocations;
    }

    /**
     * @return array{lat:float,lng:float}|null
     */
    private function resolveCoordinatePair(mixed $lat, mixed $lng): ?array
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
        ];
    }
}
