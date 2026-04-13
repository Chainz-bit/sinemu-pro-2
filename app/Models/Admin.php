<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'super_admin_id',
        'nama',
        'email',
        'username',
        'password',
        'instansi',
        'kecamatan',
        'alamat_lengkap',
        'status_verifikasi',
        'alasan_penolakan',
        'verified_at',
        'lat',
        'lng',
        'profil',
    ];

    protected $hidden = ['password'];

    public function superAdmin()
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    public function barangs()
    {
        return $this->hasMany(Barang::class);
    }

    public function klaims()
    {
        return $this->hasMany(Klaim::class);
    }

    public function notifications()
    {
        return $this->hasMany(AdminNotification::class);
    }
}
