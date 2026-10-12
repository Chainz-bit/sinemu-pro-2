<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class BarangPolicy
{
    /**
     * Tentukan apakah pengguna dapat melihat detail barang temuan.
     */
    public function view(Authenticatable $user, Barang $barang): bool
    {
        if ($user instanceof SuperAdmin) {
            return true;
        }

        if ($user instanceof Admin) {
            return is_null($barang->region_id) || (int) $barang->region_id === (int) $user->region_id;
        }

        if ($user instanceof User) {
            return (int) $barang->user_id === (int) $user->id || (bool) $barang->tampil_di_home;
        }

        return false;
    }

    /**
     * Tentukan apakah pengelola dapat mengubah data barang temuan.
     */
    public function update(Authenticatable $user, Barang $barang): bool
    {
        if ($user instanceof SuperAdmin) {
            return true;
        }

        if ($user instanceof Admin) {
            return is_null($barang->region_id) || (int) $barang->region_id === (int) $user->region_id;
        }

        return false;
    }

    /**
     * Tentukan apakah pengelola dapat mengubah status publikasi barang temuan di Home.
     */
    public function togglePublish(Authenticatable $user, Barang $barang): bool
    {
        return $this->update($user, $barang);
    }

    /**
     * Tentukan apakah pengelola dapat menghapus barang temuan.
     */
    public function delete(Authenticatable $user, Barang $barang): bool
    {
        if ($user instanceof SuperAdmin) {
            return true;
        }

        if ($user instanceof Admin) {
            return is_null($barang->region_id) || (int) $barang->region_id === (int) $user->region_id;
        }

        return false;
    }
}
