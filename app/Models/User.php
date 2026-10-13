<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $nama
 * @property string $username
 * @property string $email
 * @property string|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $google_id
 * @property string|null $avatar
 * @property string|null $nomor_telepon
 * @property string|null $alamat
 * @property string|null $profil
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, LaporanBarangHilang> $laporanHilang
 * @property-read Collection<int, Klaim> $klaims
 * @property-read Collection<int, UserNotification> $notifications
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'nama', 'username', 'email', 'email_verified_at', 'google_id', 'avatar', 'nomor_telepon', 'alamat', 'password', 'profil',
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

    public function laporanHilang(): HasMany
    {
        return $this->hasMany(LaporanBarangHilang::class);
    }

    public function klaims(): HasMany
    {
        return $this->hasMany(Klaim::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }
}
