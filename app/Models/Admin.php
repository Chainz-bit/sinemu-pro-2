<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property int|null $super_admin_id
 * @property int|null $region_id
 * @property string $nama
 * @property string $email
 * @property string|null $nomor_telepon
 * @property string $username
 * @property string $password
 * @property string|null $instansi
 * @property string|null $kecamatan
 * @property string|null $alamat_lengkap
 * @property string|null $pickup_address
 * @property float|null $pickup_lat
 * @property float|null $pickup_lng
 * @property string|null $status_verifikasi
 * @property string|null $alasan_penolakan
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property float|null $lat
 * @property float|null $lng
 * @property string|null $profil
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read SuperAdmin|null $superAdmin
 * @property-read Wilayah|null $region
 * @property-read Collection<int, Barang> $barangs
 * @property-read Collection<int, Klaim> $klaims
 * @property-read Collection<int, AdminNotification> $notifications
 */
class Admin extends Authenticatable
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_INACTIVE = 'inactive';

    public const VERIFICATION_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACTIVE,
        self::STATUS_REJECTED,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'super_admin_id',
        'region_id',
        'nama',
        'email',
        'nomor_telepon',
        'username',
        'password',
        'instansi',
        'kecamatan',
        'alamat_lengkap',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'status_verifikasi',
        'alasan_penolakan',
        'verified_at',
        'lat',
        'lng',
        'profil',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'verified_at' => 'datetime',
        'deleted_at' => 'datetime',
        'password' => 'hashed',
        'pickup_lat' => 'float',
        'pickup_lng' => 'float',
    ];

    public function isActive(): bool
    {
        return $this->status_verifikasi === self::STATUS_ACTIVE && !$this->trashed();
    }

    public function superAdmin()
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    public function region()
    {
        return $this->belongsTo(Wilayah::class, 'region_id');
    }

    public function barangs()
    {
        return $this->hasMany(Barang::class);
    }

    public function klaims()
    {
        return $this->hasMany(Klaim::class);
    }

    public function pencocokans()
    {
        return $this->hasMany(Pencocokan::class);
    }

    public function notifications()
    {
        return $this->hasMany(AdminNotification::class);
    }
}
