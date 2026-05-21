<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            WilayahSeeder::class,
            KategoriSeeder::class,
            AdminSeeder::class,
            UserSeeder::class,
            BarangHilangSeeder::class,
            BarangTemuanSeeder::class,
            KlaimSeeder::class,
            HomeDataSeeder::class,
        ]);
    }
}
