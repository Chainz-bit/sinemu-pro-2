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
        'username' => 'superadmin',
        'password' => Hash::make('password'),
    ]);

    Admin::create([
        'super_admin_id' => $super->id,
        'nama' => 'Angga Pengelola Sistem',
        'username' => 'admin',
        'password' => Hash::make('password'),
        'instansi' => 'Politeknik Negeri Indramayu',
    ]);
}
}
