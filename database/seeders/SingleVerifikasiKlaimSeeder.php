<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SingleVerifikasiKlaimSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::query()->first();
        $user = User::query()->first();

        if (!$admin || !$user) {
            return;
        }

        $laporanQuery = LaporanBarangHilang::query();
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $laporanQuery->where('sumber_laporan', 'lapor_hilang');
        }
        $laporan = $laporanQuery->first();

        $barang = Barang::query()
            ->whereIn('status_barang', ['tersedia', 'dalam_proses_klaim'])
            ->first();

        if (!$laporan || !$barang) {
            return;
        }

        Klaim::updateOrCreate(
            [
                'laporan_hilang_id' => $laporan->id,
                'barang_id' => $barang->id,
                'user_id' => $user->id,
            ],
            [
                'admin_id' => $admin->id,
                'status_klaim' => 'pending',
                'catatan' => 'Seeder satu data verifikasi klaim: menunggu tinjauan admin.',
            ]
        );

        if ($barang->status_barang === 'tersedia') {
            $barang->update(['status_barang' => 'dalam_proses_klaim']);
        }
    }
}
