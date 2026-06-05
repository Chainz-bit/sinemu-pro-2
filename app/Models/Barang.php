<?php

namespace App\Models;

use App\Support\WorkflowStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $admin_id
 * @property int|null $region_id
 * @property int|null $user_id
 * @property int|null $kategori_id
 * @property string $nama_barang
 * @property string|null $warna_barang
 * @property string|null $merek_barang
 * @property string|null $nomor_seri
 * @property string|null $deskripsi
 * @property string|null $ciri_khusus
 * @property string|null $nama_penemu
 * @property string|null $kontak_penemu
 * @property string $lokasi_ditemukan
 * @property string|null $detail_lokasi_ditemukan
 * @property string $tanggal_ditemukan
 * @property string|null $waktu_ditemukan
 * @property string|null $status_barang
 * @property string|null $foto_barang
 * @property bool $tampil_di_home
 * @property string|null $status_laporan
 * @property int|null $verified_by_admin_id
 * @property string|null $verified_at
 * @property string|null $lokasi_pengambilan
 * @property string|null $alamat_pengambilan
 * @property string|null $penanggung_jawab_pengambilan
 * @property string|null $kontak_pengambilan
 * @property string|null $jam_layanan_pengambilan
 * @property string|null $catatan_pengambilan
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Admin|null $admin
 * @property-read Wilayah|null $region
 * @property-read User|null $user
 * @property-read Kategori|null $kategori
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Klaim> $klaims
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BarangStatusHistory> $statusHistories
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Pencocokan> $pencocokans
 */
class Barang extends Model
{
    protected $fillable = [
        'admin_id',
        'region_id',
        'user_id',
        'kategori_id',
        'nama_barang',
        'warna_barang',
        'merek_barang',
        'nomor_seri',
        'deskripsi',
        'ciri_khusus',
        'nama_penemu',
        'kontak_penemu',
        'lokasi_ditemukan',
        'detail_lokasi_ditemukan',
        'tanggal_ditemukan',
        'waktu_ditemukan',
        'status_barang',
        'foto_barang',
        'tampil_di_home',
        'status_laporan',
        'verified_by_admin_id',
        'verified_at',
        'lokasi_pengambilan',
        'alamat_pengambilan',
        'penanggung_jawab_pengambilan',
        'kontak_pengambilan',
        'jam_layanan_pengambilan',
        'catatan_pengambilan',
    ];

    /**
     * @return array<int,string>
     */
    public static function adminDeletableReportStatuses(): array
    {
        return [
            WorkflowStatus::REPORT_REJECTED,
            'ditolak',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminDeletableItemStatuses(): array
    {
        return [
            '',
            WorkflowStatus::FOUND_AVAILABLE,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminEditableReportStatuses(): array
    {
        return [
            WorkflowStatus::REPORT_SUBMITTED,
            WorkflowStatus::REPORT_APPROVED,
            'pending',
            'menunggu',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminEditableItemStatuses(): array
    {
        return [
            '',
            WorkflowStatus::FOUND_AVAILABLE,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminVerifiableReportStatuses(): array
    {
        return [
            '',
            WorkflowStatus::REPORT_SUBMITTED,
            'pending',
            'menunggu',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminVerifiableItemStatuses(): array
    {
        return [
            '',
            WorkflowStatus::FOUND_AVAILABLE,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminManualStatusReportStatuses(): array
    {
        return [
            WorkflowStatus::REPORT_APPROVED,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminManualStatusCurrentStatuses(): array
    {
        return [
            '',
            WorkflowStatus::FOUND_AVAILABLE,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function allowedManualStatusTargets(): array
    {
        return [
            WorkflowStatus::FOUND_AVAILABLE,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminPublishableReportStatuses(): array
    {
        return [
            WorkflowStatus::REPORT_APPROVED,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function adminPublishableItemStatuses(): array
    {
        return [
            '',
            WorkflowStatus::FOUND_AVAILABLE,
        ];
    }

    /**
     * Apakah pengelola dapat melakukan toggle publikasi (tarik/terbitkan) laporan ini.
     * Hanya diizinkan jika status laporan adalah APPROVED dan sudah diverifikasi.
     * Tidak diizinkan saat laporan sedang dalam proses klaim (claimed/matched).
     */
    public function canTogglePublikasiByAdmin(): bool
    {
        $allowedStatuses = [
            WorkflowStatus::REPORT_APPROVED,
        ];

        return in_array(
                strtolower(trim((string) ($this->status_laporan ?? ''))),
                $allowedStatuses,
                true
            )
            && !is_null($this->verified_by_admin_id);
    }

    public function canBeDeletedByAdmin(): bool
    {
        return $this->hasAdminDeletableReportStatus()
            && !$this->hasAdminDeletionWorkflowBlocker();
    }

    public function canBeEditedByAdmin(): bool
    {
        if (!in_array($this->normalizedReportStatusForWorkflow(), self::adminEditableReportStatuses(), true)) {
            return false;
        }

        if (!in_array($this->normalizedItemStatusForWorkflow(), self::adminEditableItemStatuses(), true)) {
            return false;
        }

        if ((bool) ($this->tampil_di_home ?? false)) {
            return false;
        }

        return !$this->relationHasRecords('klaims')
            && !$this->relationHasRecords('pencocokans');
    }

    public function canBeVerifiedByAdmin(): bool
    {
        if (!in_array($this->normalizedReportStatusForWorkflow(), self::adminVerifiableReportStatuses(), true)) {
            return false;
        }

        if (!in_array($this->normalizedItemStatusForWorkflow(), self::adminVerifiableItemStatuses(), true)) {
            return false;
        }

        return !$this->relationHasRecords('klaims')
            && !$this->relationHasRecords('pencocokans');
    }

    public function canBePublishedToHomeByAdmin(): bool
    {
        if (!in_array($this->normalizedReportStatusForWorkflow(), self::adminPublishableReportStatuses(), true)) {
            return false;
        }

        if (!in_array($this->normalizedItemStatusForWorkflow(), self::adminPublishableItemStatuses(), true)) {
            return false;
        }

        if ((bool) ($this->tampil_di_home ?? false)) {
            return false;
        }

        return !$this->relationHasRecords('klaims')
            && !$this->relationHasRecords('pencocokans');
    }

    public function canHaveStatusUpdatedByAdmin(): bool
    {
        if (!in_array($this->normalizedReportStatusForWorkflow(), self::adminManualStatusReportStatuses(), true)) {
            return false;
        }

        if (!in_array($this->normalizedItemStatusForWorkflow(), self::adminManualStatusCurrentStatuses(), true)) {
            return false;
        }

        return !$this->relationHasRecords('klaims')
            && !$this->relationHasRecords('pencocokans');
    }

    public function isAllowedManualStatusTarget(string $status): bool
    {
        return in_array(
            strtolower(trim($status)),
            self::allowedManualStatusTargets(),
            true
        );
    }

    public function hasAdminDeletableReportStatus(): bool
    {
        return in_array(
            $this->normalizedReportStatusForWorkflow(),
            self::adminDeletableReportStatuses(),
            true
        );
    }

    public function hasAdminDeletionWorkflowBlocker(): bool
    {
        if ((bool) ($this->tampil_di_home ?? false)) {
            return true;
        }

        if (!in_array($this->normalizedItemStatusForWorkflow(), self::adminDeletableItemStatuses(), true)) {
            return true;
        }

        return $this->relationHasRecords('klaims')
            || $this->relationHasRecords('pencocokans');
    }

    private function normalizedReportStatusForWorkflow(): string
    {
        return strtolower(trim((string) ($this->status_laporan ?? '')));
    }

    private function normalizedItemStatusForWorkflow(): string
    {
        return strtolower(trim((string) ($this->status_barang ?? '')));
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

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function region()
    {
        return $this->belongsTo(Wilayah::class, 'region_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }

    public function klaims()
    {
        return $this->hasMany(Klaim::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(BarangStatusHistory::class)->latest();
    }

    public function pencocokans()
    {
        return $this->hasMany(Pencocokan::class);
    }
}
