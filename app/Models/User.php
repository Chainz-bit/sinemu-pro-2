<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nama', 'username', 'email', 'password', 'profil',
    ];

    protected $hidden = ['password', 'remember_token'];

    public function laporanHilang()
    {
        return $this->hasMany(LaporanBarangHilang::class);
    }

    public function klaims()
    {
        return $this->hasMany(Klaim::class);
    }
}