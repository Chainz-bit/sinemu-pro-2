<?php

namespace Database\Seeders;

use App\Models\Kategori;
use Illuminate\Database\Seeder;

class KategoriSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Dompet',
            'Elektronik',
            'Kunci',
            'Dokumen & Kartu',
            'Tas & Ransel',
            'Aksesoris & Perhiasan',
            'Pakaian & Sepatu',
            'Lain-lain',
        ];

        foreach ($categories as $name) {
            Kategori::query()->firstOrCreate([
                'nama_kategori' => $name,
            ]);
        }
    }
}
