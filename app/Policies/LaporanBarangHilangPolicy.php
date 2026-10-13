<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class LaporanBarangHilangPolicy
{
    /**
     * Tentukan apakah pengguna dapat melihat laporan barang hilang.
     */
    public function view(Authenticatable $user, LaporanBarangHilang $laporan): bool
    {
        if ($user instanceof SuperAdmin) {
            return true;
        }

        if ($user instanceof Admin) {
            return is_null($laporan->region_id) || (int) $laporan->region_id === (int) $user->region_id;
        }

        if ($user instanceof User) {
            return (int) $laporan->user_id === (int) $user->id || (bool) $laporan->tampil_di_home;
        }

        return false;
    }

    /**
     * Tentukan apakah pengguna atau pengelola dapat mengedit laporan barang hilang.
     */
    public function update(Authenticatable $user, LaporanBarangHilang $laporan): bool
    {
        if ($user instanceof SuperAdmin) {
            return true;
        }

        if ($user instanceof Admin) {
            return is_null($laporan->region_id) || (int) $laporan->region_id === (int) $user->region_id;
        }

        if ($user instanceof User) {
            return (int) $laporan->user_id === (int) $user->id;
        }

        return false;
    }

    /**
     * Tentukan apakah pengelola dapat mengubah status publikasi laporan barang hilang di Home.
     */
    public function togglePublish(Authenticatable $user, LaporanBarangHilang $laporan): bool
    {
        return $this->update($user, $laporan);
    }

    /**
     * Tentukan apakah pengguna atau pengelola dapat menghapus laporan barang hilang.
     */
    public function delete(Authenticatable $user, LaporanBarangHilang $laporan): bool
    {
        if ($user instanceof SuperAdmin) {
            return true;
        }

        if ($user instanceof Admin) {
            return is_null($laporan->region_id) || (int) $laporan->region_id === (int) $user->region_id;
        }

        if ($user instanceof User) {
            return (int) $laporan->user_id === (int) $user->id;
        }

        return false;
    }
}
