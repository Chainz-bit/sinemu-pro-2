<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\User;
use Illuminate\Database\Seeder;

class KlaimSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Admin::where('username', 'admin')->first();
        $user = User::where('email', 'user@example.com')->first();

        if (! $admin || ! $user) {
            return;
        }

        $klaimItems = [
            [
                'laporan_nama' => 'Dompet Kulit Coklat',
                'barang_nama' => 'Map Dokumen STNK',
                'status_klaim' => 'pending',
                'catatan' => 'Pemilik sedang melengkapi bukti kepemilikan.',
            ],
            [
                'laporan_nama' => 'Kunci Motor Honda',
                'barang_nama' => 'Jam Tangan Casio Hitam',
                'status_klaim' => 'ditolak',
                'catatan' => 'Ciri barang tidak sesuai dengan laporan kehilangan.',
            ],
            [
                'laporan_nama' => 'Tas Ransel Hitam',
                'barang_nama' => 'Smartphone Samsung Galaxy A52',
                'status_klaim' => 'disetujui',
                'catatan' => 'Bukti kepemilikan valid dan sudah diverifikasi admin.',
            ],
        ];

        foreach ($klaimItems as $item) {
            $laporan = LaporanBarangHilang::where('nama_barang', $item['laporan_nama'])->first();
            $barang = Barang::where('nama_barang', $item['barang_nama'])->first();

            if (! $laporan || ! $barang) {
                continue;
            }

            Klaim::updateOrCreate(
                [
                    'laporan_hilang_id' => $laporan->id,
                    'barang_id' => $barang->id,
                    'user_id' => $user->id,
                ],
                [
                    'admin_id' => $admin->id,
                    'status_klaim' => $item['status_klaim'],
                    'catatan' => $item['catatan'],
                ]
            );

            if ($item['status_klaim'] === 'disetujui') {
                $barang->update(['status_barang' => 'sudah_diklaim']);
            } elseif ($item['status_klaim'] === 'pending') {
                $barang->update(['status_barang' => 'dalam_proses_klaim']);
            }
        }
    }
}
