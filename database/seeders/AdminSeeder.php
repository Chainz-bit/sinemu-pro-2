<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SuperAdmin;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
public function run(): void
{
    $super = SuperAdmin::create([
        'nama' => 'Super Admin',
        'email' => 'superadmin@sinemu.com',
        'username' => 'superadmin',
        'password' => Hash::make('super123'),
    ]);

    Admin::create([
        'super_admin_id' => $super->id,
        'nama' => 'Angga Pengelola Sistem',
        'email' => 'admin@sinemu.local',
        'username' => 'admin',
        'password' => Hash::make('password'),
        'instansi' => 'Politeknik Negeri Indramayu',
        'kecamatan' => 'Indramayu Kota',
        'alamat_lengkap' => 'Jl. Jenderal Sudirman No. 88, Indramayu',
        'status_verifikasi' => 'active',
        'verified_at' => now(),
    ]);
}
}
