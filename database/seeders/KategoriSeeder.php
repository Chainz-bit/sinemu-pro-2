<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Kategori;

class KategoriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
public function run(): void
{
    Kategori::insert([
        ['nama_kategori' => 'Elektronik'],
        ['nama_kategori' => 'Dokumen'],
        ['nama_kategori' => 'Aksesoris'],
        ['nama_kategori' => 'Kendaraan'],
    ]);
}
}
