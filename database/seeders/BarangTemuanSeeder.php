<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BarangTemuanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = SuperAdmin::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'nama' => 'Super Admin',
                'email' => 'superadmin@sinemu.com',
                'password' => Hash::make('super123'),
            ]
        );

        $admin = Admin::firstOrCreate(
            ['username' => 'admin'],
            [
                'super_admin_id' => $superAdmin->id,
                'nama' => 'Admin Kecamatan',
                'email' => 'admin@sinemu.local',
                'password' => Hash::make('password'),
                'instansi' => 'Kecamatan Indramayu',
                'kecamatan' => 'Indramayu Kota',
                'alamat_lengkap' => 'Jl. Jenderal Sudirman No. 88, Indramayu',
                'status_verifikasi' => 'active',
                'verified_at' => now(),
            ]
        );

        $kategoriElektronik = Kategori::firstOrCreate(['nama_kategori' => 'Elektronik']);
        $kategoriDokumen = Kategori::firstOrCreate(['nama_kategori' => 'Dokumen']);
        $kategoriAksesoris = Kategori::firstOrCreate(['nama_kategori' => 'Aksesoris']);

        $foundItems = [
            [
                'kategori_id' => $kategoriElektronik->id,
                'nama_barang' => 'Smartphone Samsung Galaxy A52',
                'deskripsi' => 'Ditemukan di kursi ruang tunggu terminal.',
                'lokasi_ditemukan' => 'Terminal Sindang',
                'tanggal_ditemukan' => '2026-03-29',
                'status_barang' => 'tersedia',
            ],
            [
                'kategori_id' => $kategoriDokumen->id,
                'nama_barang' => 'Map Dokumen STNK',
                'deskripsi' => 'Map biru berisi STNK dan fotokopi KTP.',
                'lokasi_ditemukan' => 'Area Parkir Pasar Jatibarang',
                'tanggal_ditemukan' => '2026-03-31',
                'status_barang' => 'dalam_proses_klaim',
            ],
            [
                'kategori_id' => $kategoriAksesoris->id,
                'nama_barang' => 'Jam Tangan Casio Hitam',
                'deskripsi' => 'Jam tangan digital ditemukan di trotoar.',
                'lokasi_ditemukan' => 'Alun-Alun Indramayu',
                'tanggal_ditemukan' => '2026-04-03',
                'status_barang' => 'tersedia',
            ],
        ];

        foreach ($foundItems as $item) {
            Barang::updateOrCreate(
                [
                    'nama_barang' => $item['nama_barang'],
                    'lokasi_ditemukan' => $item['lokasi_ditemukan'],
                ],
                [
                    'admin_id' => $admin->id,
                    'kategori_id' => $item['kategori_id'],
                    'deskripsi' => $item['deskripsi'],
                    'tanggal_ditemukan' => $item['tanggal_ditemukan'],
                    'status_barang' => $item['status_barang'],
                ]
            );
        }
    }
}
