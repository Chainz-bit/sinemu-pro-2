<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Kategori;

class KategoriSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Elektronik',
            'Dokumen',
            'Dompet',
            'Kunci',
            'Tas',
            'Aksesoris',
            'Kendaraan',
            'Pakaian',
            'Perhiasan',
            'Uang',
            'Kartu Identitas',
            'Buku atau Alat Tulis',
            'Mainan',
            'Perlengkapan Pribadi',
            'Lainnya',
        ];

        foreach ($categories as $name) {
            Kategori::query()->firstOrCreate([
                'nama_kategori' => $name,
            ]);
        }
    }
}