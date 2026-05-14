<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $nama
 * @property string $username
 * @property string $email
 * @property string|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $nomor_telepon
 * @property string|null $profil
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Collection<int, LaporanBarangHilang> $laporanHilang
 * @property-read Collection<int, Klaim> $klaims
 * @property-read Collection<int, UserNotification> $notifications
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'nama', 'username', 'email', 'nomor_telepon', 'password', 'profil',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getNamaAttribute($value): string
    {
        return (string) ($value ?? $this->attributes['name'] ?? '');
    }

    public function setNamaAttribute($value): void
    {
        $this->attributes['nama'] = $value;
        $this->attributes['name'] = $value;
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;
        if (array_key_exists('nama', $this->attributes)) {
            $this->attributes['nama'] = $value;
        }
    }

    public function laporanHilang()
    {
        return $this->hasMany(LaporanBarangHilang::class);
    }

    public function klaims()
    {
        return $this->hasMany(Klaim::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }
}
