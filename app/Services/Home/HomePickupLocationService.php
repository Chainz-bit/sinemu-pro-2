<?php

namespace App\Services\Home;

use App\Models\Admin;

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

                $pickupQuery = Admin::query()
                    ->with(['region:id,nama_wilayah,lat,lng'])
                    ->where('status_verifikasi', Admin::STATUS_ACTIVE)
                    ->whereNotNull('region_id')
                    ->whereHas('region', function ($query) {
                        $query->whereNotNull('lat')
                            ->whereNotNull('lng');
                    })
                    ->orderBy('instansi');

                return $pickupQuery
                    ->get($selectColumns)
                    ->filter(function (Admin $admin) {
                        return $admin->region
                            && is_numeric($admin->region->lat)
                            && is_numeric($admin->region->lng);
                    })
                    ->map(function (Admin $admin) use ($hasKecamatan, $hasAlamatLengkap) {
                        $instansi = trim((string) ($admin->instansi ?? ''));
                        $kecamatan = $hasKecamatan ? trim((string) ($admin->kecamatan ?? '')) : '';
                        $alamatLengkap = $hasAlamatLengkap ? trim((string) ($admin->alamat_lengkap ?? '')) : '';
                        $address = $alamatLengkap !== ''
                            ? $alamatLengkap
                            : ($kecamatan !== '' ? ('Kecamatan ' . $kecamatan) : ($instansi !== '' ? $instansi : 'Alamat belum tersedia'));

                        return [
                            'id' => $admin->id,
                            'name' => $instansi !== '' ? $instansi : \App\Support\RoleLabels::manager() . ' SiNemu',
                            'manager_label' => \App\Support\RoleLabels::manager(),
                            'address' => $address,
                            'kecamatan' => $kecamatan,
                            'lat' => (float) $admin->region->lat,
                            'lng' => (float) $admin->region->lng,
                            'phone' => '0851-7438-6642',
                            'hours' => '08.00-20.00 WIB',
                        ];
                    })
                    ->values()
                    ->all();
            }, []);
        }

        return $pickupLocations;
    }
}
