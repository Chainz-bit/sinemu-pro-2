<?php

namespace App\Models;

use App\Support\WorkflowStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $region_id
 * @property int|null $kategori_id
 * @property string $nama_barang
 * @property string|null $kategori_barang
 * @property string|null $warna_barang
 * @property string|null $merek_barang
 * @property string|null $nomor_seri
 * @property string $lokasi_hilang
 * @property string|null $detail_lokasi_hilang
 * @property string $tanggal_hilang
 * @property string|null $waktu_hilang
 * @property string|null $keterangan
 * @property string|null $ciri_khusus
 * @property string|null $kontak_pelapor
 * @property string|null $bukti_kepemilikan
 * @property string|null $foto_barang
 * @property string|null $sumber_laporan
 * @property bool $tampil_di_home
 * @property string|null $status_laporan
 * @property int|null $verified_by_admin_id
 * @property string|null $verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Wilayah|null $region
 * @property-read Kategori|null $kategori
 * @property-read Collection<int, Klaim> $klaims
 * @property-read Collection<int, Pencocokan> $pencocokans
 */
class LaporanBarangHilang extends Model
{
    protected $fillable = [
        'user_id',
        'region_id',
        'kategori_id',
        'nama_barang',
        'kategori_barang',
        'warna_barang',
        'merek_barang',
        'nomor_seri',
        'lokasi_hilang',
        'detail_lokasi_hilang',
        'tanggal_hilang',
        'waktu_hilang',
        'keterangan',
        'ciri_khusus',
        'kontak_pelapor',
        'bukti_kepemilikan',
        'foto_barang',
        'sumber_laporan',
        'tampil_di_home',
        'status_laporan',
        'verified_by_admin_id',
        'verified_at',
    ];

    /**
     * @return array<int,string>
     */
    public static function adminBlockedDeletionStatuses(): array
    {
        return [
            WorkflowStatus::REPORT_SUBMITTED,
            WorkflowStatus::REPORT_APPROVED,
            WorkflowStatus::REPORT_MATCHED,
            WorkflowStatus::REPORT_CLAIMED,
            WorkflowStatus::REPORT_COMPLETED,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminDeletableStatuses(): array
    {
        return [
            WorkflowStatus::REPORT_REJECTED,
            'ditolak',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminVerifiableStatuses(): array
    {
        return [
            WorkflowStatus::REPORT_SUBMITTED,
            'pending',
            'menunggu',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminEditableStatuses(): array
    {
        return [
            WorkflowStatus::REPORT_SUBMITTED,
            WorkflowStatus::REPORT_APPROVED,
            'pending',
            'menunggu',
        ];
    }

    public function canBeDeletedByAdmin(): bool
    {
        return in_array(
            strtolower(trim((string) ($this->status_laporan ?? ''))),
            self::adminDeletableStatuses(),
            true
        );
    }

    public function canBeVerifiedByAdmin(): bool
    {
        return in_array(
            strtolower(trim((string) ($this->status_laporan ?? ''))),
            self::adminVerifiableStatuses(),
            true
        );
    }

    public function canBeEditedByAdmin(): bool
    {
        if (!in_array(
            strtolower(trim((string) ($this->status_laporan ?? ''))),
            self::adminEditableStatuses(),
            true
        )) {
            return false;
        }

        return !$this->relationHasRecords('klaims')
            && !$this->relationHasRecords('pencocokans');
    }

    private function relationHasRecords(string $relation): bool
    {
        $countKey = $relation . '_count';
        if (array_key_exists($countKey, $this->attributes)) {
            return (int) $this->attributes[$countKey] > 0;
        }

        if ($this->relationLoaded($relation)) {
            return $this->getRelation($relation)->isNotEmpty();
        }

        return $this->{$relation}()->exists();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function region()
    {
        return $this->belongsTo(Wilayah::class, 'region_id');
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }

    public function klaims()
    {
        return $this->hasMany(Klaim::class, 'laporan_hilang_id');
    }

    public function pencocokans()
    {
        return $this->hasMany(Pencocokan::class, 'laporan_hilang_id');
    }
}
