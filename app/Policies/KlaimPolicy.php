<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Klaim;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class KlaimPolicy
{
    /**
     * Tentukan apakah pengguna dapat melihat klaim (misal: halaman tiket atau verifikasi).
     */
    public function view(Authenticatable $user, Klaim $klaim): bool
    {
        if ($user instanceof SuperAdmin) {
            return true;
        }

        if ($user instanceof User) {
            return (int) $klaim->user_id === (int) $user->id;
        }

        if ($user instanceof Admin) {
            return $this->canAdminAccessClaim($user, $klaim);
        }

        return false;
    }

    /**
     * Tentukan apakah pengguna dapat membuat klaim baru.
     */
    public function create(Authenticatable $user): bool
    {
        return $user instanceof User;
    }

    /**
     * Tentukan apakah pengelola dapat memverifikasi klaim (approve / reject).
     */
    public function verify(Authenticatable $user, Klaim $klaim): bool
    {
        if ($user instanceof SuperAdmin) {
            return true;
        }

        if ($user instanceof Admin) {
            if ($user->status_verifikasi !== Admin::STATUS_ACTIVE) {
                return false;
            }

            return $this->canAdminAccessClaim($user, $klaim);
        }

        return false;
    }

    /**
     * Tentukan apakah pengelola dapat menyelesaikan klaim / serah terima.
     */
    public function complete(Authenticatable $user, Klaim $klaim): bool
    {
        return $this->verify($user, $klaim);
    }

    /**
     * Tentukan apakah pengguna dapat menghapus klaim.
     */
    public function delete(Authenticatable $user, Klaim $klaim): bool
    {
        if ($user instanceof SuperAdmin) {
            return true;
        }

        if ($user instanceof User) {
            return (int) $klaim->user_id === (int) $user->id;
        }

        if ($user instanceof Admin) {
            return $this->canAdminAccessClaim($user, $klaim);
        }

        return false;
    }

    private function canAdminAccessClaim(Admin $admin, Klaim $klaim): bool
    {
        if (empty($admin->region_id)) {
            return false;
        }

        $adminRegionId = (int) $admin->region_id;
        $klaim->loadMissing(['barang:id,region_id', 'laporanHilang:id,region_id']);

        $barangRegionId = $klaim->barang?->region_id;
        $laporanRegionId = $klaim->laporanHilang?->region_id;

        if (! is_null($klaim->admin_id)) {
            if ((int) $klaim->admin_id !== (int) $admin->id) {
                return false;
            }

            $hasExplicitRegion = ! is_null($barangRegionId) || ! is_null($laporanRegionId);
            if ($hasExplicitRegion) {
                $matchesBarang = ! is_null($barangRegionId) && (int) $barangRegionId === $adminRegionId;
                $matchesLaporan = ! is_null($laporanRegionId) && (int) $laporanRegionId === $adminRegionId;

                return $matchesBarang || $matchesLaporan;
            }

            return true;
        }

        return (! is_null($barangRegionId) && (int) $barangRegionId === $adminRegionId)
            || (! is_null($laporanRegionId) && (int) $laporanRegionId === $adminRegionId);
    }
}
