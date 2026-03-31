<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['super_admin_id', 'nama', 'username', 'password', 'instansi', 'profil'];
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
}
