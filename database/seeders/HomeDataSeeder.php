<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class HomeDataSeeder extends Seeder
{
    /**
     * Seed data untuk halaman beranda (filter, daftar hilang, daftar temuan).
     */
    public function run(): void
    {
        $admin = null;
        if (Schema::hasTable('super_admins') && Schema::hasTable('admins')) {
            $superAdmin = SuperAdmin::firstOrCreate(
                ['username' => 'superadmin'],
                [
                    'nama' => 'Super Admin',
                    'email' => 'superadmin@sinemu.com',
                    'password' => Hash::make('super123'),
                ]
            );

            if (empty($superAdmin->email)) {
                $superAdmin->update(['email' => 'superadmin@sinemu.com']);
            }

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

            if (($admin->status_verifikasi ?? null) !== 'active') {
                $admin->update([
                    'status_verifikasi' => 'active',
                    'verified_at' => now(),
                ]);
            }
        }

        $user = null;
        if (Schema::hasTable('users')) {
            $hasUsername = Schema::hasColumn('users', 'username');
            $hasEmail = Schema::hasColumn('users', 'email');

            $userIdentity = $hasUsername
                ? ['username' => 'user']
                : ['email' => 'user@example.com'];

            $userPayload = ['password' => Hash::make('password')];
            if ($hasUsername) {
                $userPayload['username'] = 'user';
            }
            if ($hasEmail) {
                $userPayload['email'] = 'user@example.com';
            }
            if (Schema::hasColumn('users', 'nama')) {
                $userPayload['nama'] = 'User Biasa';
            }
            if (Schema::hasColumn('users', 'name')) {
                $userPayload['name'] = 'User Biasa';
            }
            $user = User::firstOrCreate($userIdentity, $userPayload);
        }

        $categoryNames = [
            'Aksesoris',
            'Dokumen',
            'Elektronik',
            'Gadget',
        ];

        if (!Schema::hasTable('kategoris')) {
            return;
        }

        foreach ($categoryNames as $name) {
            Kategori::firstOrCreate(['nama_kategori' => $name]);
        }

        if (Schema::hasTable('kategoris') && Schema::hasTable('barangs')) {
            $elektronikId = DB::table('kategoris')->where('nama_kategori', 'Elektronik')->value('id');
            $removedIds = DB::table('kategoris')->whereIn('nama_kategori', ['Hewan', 'Otomotif'])->pluck('id');

            if ($elektronikId && $removedIds->isNotEmpty()) {
                DB::table('barangs')
                    ->whereIn('kategori_id', $removedIds)
                    ->update(['kategori_id' => $elektronikId]);
            }

            DB::table('kategoris')->whereIn('nama_kategori', ['Hewan', 'Otomotif'])->delete();
        }

        if (Schema::hasTable('wilayahs')) {
            $wilayahItems = [
                ['nama_wilayah' => 'Kecamatan Indramayu', 'lat' => -6.3275000, 'lng' => 108.3207000],
                ['nama_wilayah' => 'Kecamatan Lohbener', 'lat' => -6.3852000, 'lng' => 108.2793000],
                ['nama_wilayah' => 'Kecamatan Pasekan', 'lat' => -6.3201000, 'lng' => 108.3388000],
                ['nama_wilayah' => 'Kecamatan Balongan', 'lat' => -6.3502000, 'lng' => 108.4108000],
                ['nama_wilayah' => 'Kecamatan Jatibarang', 'lat' => -6.4741000, 'lng' => 108.3061000],
                ['nama_wilayah' => 'Kecamatan Haurgeulis', 'lat' => -6.4477000, 'lng' => 107.9398000],
                ['nama_wilayah' => 'Kecamatan Bangodua', 'lat' => -6.4941000, 'lng' => 108.1455000],
                ['nama_wilayah' => 'Kecamatan Sliyeg', 'lat' => -6.4406000, 'lng' => 108.3693000],
                ['nama_wilayah' => 'Kecamatan Kandanghaur', 'lat' => -6.3433000, 'lng' => 107.9816000],
                ['nama_wilayah' => 'Kecamatan Krangkeng', 'lat' => -6.4415000, 'lng' => 108.4841000],
            ];

            foreach ($wilayahItems as $wilayah) {
                Wilayah::updateOrCreate(
                    ['nama_wilayah' => $wilayah['nama_wilayah']],
                    ['lat' => $wilayah['lat'], 'lng' => $wilayah['lng']]
                );
            }
        }

        $kategoriMap = Kategori::query()
            ->pluck('id', 'nama_kategori')
            ->toArray();

        $foundItems = [
            [
                'kategori' => 'Dokumen',
                'nama_barang' => 'Map Berisi STNK dan SIM',
                'deskripsi' => 'Dokumen kendaraan ditemukan di area parkir pasar.',
                'lokasi_ditemukan' => 'Kecamatan Indramayu',
                'tanggal_ditemukan' => '2026-03-19',
                'status_barang' => 'tersedia',
            ],
            [
                'kategori' => 'Aksesoris',
                'nama_barang' => 'Jam Tangan Casio',
                'deskripsi' => 'Jam tangan digital ditemukan di trotoar.',
                'lokasi_ditemukan' => 'Kecamatan Lohbener',
                'tanggal_ditemukan' => '2026-03-22',
                'status_barang' => 'tersedia',
            ],
            [
                'kategori' => 'Gadget',
                'nama_barang' => 'iPhone 13 Hitam',
                'deskripsi' => 'Ponsel ditemukan di halte bus.',
                'lokasi_ditemukan' => 'Kecamatan Pasekan',
                'tanggal_ditemukan' => '2026-03-27',
                'status_barang' => 'dalam_proses_klaim',
            ],
            [
                'kategori' => 'Elektronik',
                'nama_barang' => 'Kunci Mobil Honda',
                'deskripsi' => 'Satu set kunci mobil ditemukan di parkiran minimarket.',
                'lokasi_ditemukan' => 'Kecamatan Balongan',
                'tanggal_ditemukan' => '2026-03-28',
                'status_barang' => 'tersedia',
            ],
        ];

        if (Schema::hasTable('barangs') && $admin) {
            foreach ($foundItems as $item) {
                $kategoriId = $kategoriMap[$item['kategori']] ?? null;
                if (!$kategoriId) {
                    continue;
                }

                Barang::updateOrCreate(
                    [
                        'nama_barang' => $item['nama_barang'],
                        'lokasi_ditemukan' => $item['lokasi_ditemukan'],
                    ],
                    [
                        'admin_id' => $admin->id,
                        'kategori_id' => $kategoriId,
                        'deskripsi' => $item['deskripsi'],
                        'tanggal_ditemukan' => $item['tanggal_ditemukan'],
                        'status_barang' => $item['status_barang'],
                    ]
                );
            }
        }

        $lostItems = [
            [
                'nama_barang' => 'Dompet Kulit Coklat',
                'lokasi_hilang' => 'Kecamatan Indramayu',
                'tanggal_hilang' => '2026-03-18',
                'keterangan' => 'Berisi KTP dan kartu ATM.',
            ],
            [
                'nama_barang' => 'Anjing Golden "Bud"',
                'lokasi_hilang' => 'Kecamatan Lohbener',
                'tanggal_hilang' => '2026-03-21',
                'keterangan' => 'Anjing peliharaan warna krem.',
            ],
            [
                'nama_barang' => 'Kunci Motor',
                'lokasi_hilang' => 'Kecamatan Pasekan',
                'tanggal_hilang' => '2026-03-25',
                'keterangan' => 'Gantungan warna merah.',
            ],
            [
                'nama_barang' => 'Tas Ransel Hitam',
                'lokasi_hilang' => 'Kecamatan Balongan',
                'tanggal_hilang' => '2026-03-27',
                'keterangan' => 'Tas berisi dokumen kerja.',
            ],
        ];

        if (Schema::hasTable('laporan_barang_hilangs') && $user) {
            foreach ($lostItems as $item) {
                $payload = [
                    'user_id' => $user->id,
                    'tanggal_hilang' => $item['tanggal_hilang'],
                    'keterangan' => $item['keterangan'],
                ];

                if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
                    $payload['sumber_laporan'] = 'lapor_hilang';
                }

                LaporanBarangHilang::updateOrCreate(
                    [
                        'nama_barang' => $item['nama_barang'],
                        'lokasi_hilang' => $item['lokasi_hilang'],
                    ],
                    $payload
                );
            }
        }
    }
}
