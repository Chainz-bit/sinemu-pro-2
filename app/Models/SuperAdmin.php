<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class SuperAdmin extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['nama', 'username', 'password', 'profil'];
    protected $hidden = ['password'];

    public function admins()
    {
        return $this->hasMany(Admin::class);
    }
}
