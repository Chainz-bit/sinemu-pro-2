<?php

namespace Database\Seeders;

use App\Models\LaporanBarangHilang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SingleBarangHilangSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'User Biasa',
                'nama' => 'User Biasa',
                'username' => 'user',
                'password' => Hash::make('password'),
            ]
        );

        $payload = [
            'user_id' => $user->id,
            'nama_barang' => 'Kartu Identitas Mahasiswa',
            'lokasi_hilang' => 'Perpustakaan Kampus',
            'tanggal_hilang' => '2026-04-10',
            'keterangan' => 'Kartu identitas warna biru dalam holder transparan.',
        ];

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $payload['sumber_laporan'] = 'lapor_hilang';
        }

        LaporanBarangHilang::updateOrCreate(
            [
                'user_id' => $payload['user_id'],
                'nama_barang' => $payload['nama_barang'],
                'lokasi_hilang' => $payload['lokasi_hilang'],
            ],
            [
                'tanggal_hilang' => $payload['tanggal_hilang'],
                'keterangan' => $payload['keterangan'],
                'sumber_laporan' => $payload['sumber_laporan'] ?? null,
            ]
        );
    }
}
