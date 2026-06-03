<?php

namespace App\Models;

use App\Support\WorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $laporan_hilang_id
 * @property int|null $barang_id
 * @property int|null $pencocokan_id
 * @property int $user_id
 * @property int|null $admin_id
 * @property string $status_klaim
 * @property string|null $status_verifikasi
 * @property string|null $catatan
 * @property string|null $kontak
 * @property array|null $bukti_foto
 * @property string|null $bukti_kepemilikan
 * @property string|null $bukti_ciri_khusus
 * @property string|null $bukti_detail_isi
 * @property string|null $bukti_lokasi_spesifik
 * @property string|null $bukti_waktu_hilang
 * @property array|null $hasil_checklist
 * @property float|null $skor_validitas
 * @property string|null $catatan_verifikasi_admin
 * @property string|null $alasan_penolakan
 * @property string|null $diverifikasi_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read LaporanBarangHilang|null $laporanHilang
 * @property-read Barang|null $barang
 * @property-read Pencocokan|null $pencocokan
 * @property-read User $user
 * @property-read Admin|null $admin
 */
class Klaim extends Model
{
    protected $fillable = [
        'laporan_hilang_id',
        'barang_id',
        'pencocokan_id',
        'user_id',
        'admin_id',
        'status_klaim',
        'status_verifikasi',
        'catatan',
        'kontak',
        'bukti_foto',
        'bukti_kepemilikan',
        'bukti_ciri_khusus',
        'bukti_detail_isi',
        'bukti_lokasi_spesifik',
        'bukti_waktu_hilang',
        'hasil_checklist',
        'skor_validitas',
        'catatan_verifikasi_admin',
        'alasan_penolakan',
        'diverifikasi_at',
    ];

    protected $casts = [
        'bukti_foto' => 'array',
        'hasil_checklist' => 'array',
        'diverifikasi_at' => 'datetime',
    ];

    /**
     * @return array<int,string>
     */
    public static function activeLegacyStatuses(): array
    {
        return [
            WorkflowStatus::CLAIM_LEGACY_PENDING,
            WorkflowStatus::CLAIM_LEGACY_APPROVED,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function activeVerificationStatuses(): array
    {
        return [
            WorkflowStatus::CLAIM_SUBMITTED,
            WorkflowStatus::CLAIM_UNDER_REVIEW,
            WorkflowStatus::CLAIM_APPROVED,
        ];
    }

    public function scopeActiveForSubmission(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereIn('status_klaim', self::activeLegacyStatuses())
                ->orWhereIn('status_verifikasi', self::activeVerificationStatuses());
        });
    }

    public function canBeDeleted(): bool
    {
        $claimStatus = strtolower(trim((string) $this->status_klaim));
        $verificationStatus = strtolower(trim((string) ($this->status_verifikasi ?? '')));
        $blockedStatuses = [
            ...self::activeLegacyStatuses(),
            ...self::activeVerificationStatuses(),
            WorkflowStatus::CLAIM_COMPLETED,
        ];

        if (in_array($claimStatus, $blockedStatuses, true) || in_array($verificationStatus, $blockedStatuses, true)) {
            return false;
        }

        return in_array($claimStatus, [WorkflowStatus::CLAIM_LEGACY_REJECTED, WorkflowStatus::CLAIM_REJECTED], true)
            || $verificationStatus === WorkflowStatus::CLAIM_REJECTED;
    }

    public function laporanHilang()
    {
        return $this->belongsTo(LaporanBarangHilang::class, 'laporan_hilang_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function pencocokan()
    {
        return $this->belongsTo(Pencocokan::class, 'pencocokan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
