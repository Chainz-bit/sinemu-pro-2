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
                $hasKecamatan = $hasDatabaseColumn('admins', 'kecamatan');
                $hasAlamatLengkap = $hasDatabaseColumn('admins', 'alamat_lengkap');
                $hasLat = $hasDatabaseColumn('admins', 'lat');
                $hasLng = $hasDatabaseColumn('admins', 'lng');

                $selectColumns = ['id', 'instansi'];
                if ($hasKecamatan) {
                    $selectColumns[] = 'kecamatan';
                }
                if ($hasAlamatLengkap) {
                    $selectColumns[] = 'alamat_lengkap';
                }
                if ($hasLat) {
                    $selectColumns[] = 'lat';
                }
                if ($hasLng) {
                    $selectColumns[] = 'lng';
                }

                $pickupQuery = Admin::query()->orderBy('instansi');

                if ($hasStatusVerifikasi) {
                    $pickupQuery->where('status_verifikasi', 'active');
                }

                return $pickupQuery
                    ->get($selectColumns)
                    ->map(function (Admin $admin) use ($hasKecamatan, $hasAlamatLengkap, $hasLat, $hasLng) {
                        $instansi = trim((string) ($admin->instansi ?? ''));
                        $kecamatan = $hasKecamatan ? trim((string) ($admin->kecamatan ?? '')) : '';
                        $alamatLengkap = $hasAlamatLengkap ? trim((string) ($admin->alamat_lengkap ?? '')) : '';
                        $address = $alamatLengkap !== ''
                            ? $alamatLengkap
                            : ($kecamatan !== '' ? ('Kecamatan ' . $kecamatan) : ($instansi !== '' ? $instansi : 'Alamat belum tersedia'));

                        return [
                            'id' => $admin->id,
                            'name' => $instansi !== '' ? $instansi : 'Admin SiNemu',
                            'manager_label' => 'Admin Pengelola',
                            'address' => $address,
                            'kecamatan' => $kecamatan,
                            'lat' => $hasLat && $admin->lat !== null ? (float) $admin->lat : null,
                            'lng' => $hasLng && $admin->lng !== null ? (float) $admin->lng : null,
                            'phone' => '0812-3456-7890',
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
