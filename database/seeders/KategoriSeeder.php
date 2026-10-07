<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Kategori;

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