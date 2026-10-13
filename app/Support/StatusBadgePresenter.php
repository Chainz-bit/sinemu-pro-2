<?php

namespace App\Support;

final class StatusBadgePresenter
{
    /**
     * Resolve status details into a standardized structure.
     *
     * @return array{key: string, label: string, color: string, semantic: string, legacyClass: string}
     */
    public static function resolve(mixed $status, ?string $type = null): array
    {
        $rawStatus = is_object($status) ? self::extractStatusFromObject($status, $type) : (string) ($status ?? '');
        $inferredType = $type ?? self::inferType($status, $rawStatus);

        return match ($inferredType) {
            'claim' => self::resolveClaimStatus($rawStatus),
            'report' => self::resolveReportStatus($rawStatus),
            'found_item' => self::resolveFoundItemStatus($rawStatus),
            'admin' => self::resolveAdminStatus($rawStatus),
            'log' => self::resolveLogStatus($rawStatus),
            default => self::resolveGeneralStatus($rawStatus),
        };
    }

    /**
     * Infer the status domain type if not explicitly passed.
     */
    private static function inferType(mixed $source, string $rawStatus): string
    {
        if (is_object($source)) {
            if (isset($source->status_klaim) || (isset($source->status_verifikasi) && isset($source->barang_id))) {
                return 'claim';
            }
            if (isset($source->status_laporan)) {
                return 'report';
            }
            if (isset($source->status_barang)) {
                return 'found_item';
            }
            if (isset($source->instansi) || isset($source->kecamatan)) {
                return 'admin';
            }
        }

        $clean = strtolower(trim($rawStatus));

        if (in_array($clean, ['tersedia', 'dalam_proses_klaim', 'sudah_diklaim', 'sudah_dikembalikan'], true)) {
            return 'found_item';
        }

        if (in_array($clean, ['active', 'inactive'], true)) {
            return 'admin';
        }

        if (in_array($clean, ['unread', 'read', 'belum_dibaca', 'sudah_dibaca'], true)) {
            return 'log';
        }

        return 'claim';
    }

    private static function extractStatusFromObject(object $object, ?string $type): string
    {
        if ($type === 'found_item' || isset($object->status_barang)) {
            return (string) ($object->status_barang ?? '');
        }

        if ($type === 'report' || isset($object->status_laporan)) {
            return (string) ($object->status_laporan ?? '');
        }

        if ($type === 'admin' || (isset($object->status_verifikasi) && (isset($object->instansi) || isset($object->kecamatan)))) {
            return (string) ($object->status_verifikasi ?? '');
        }

        if (isset($object->status_klaim) || isset($object->status_verifikasi)) {
            return ClaimStatusPresenter::key(
                isset($object->status_klaim) ? (string) $object->status_klaim : null,
                isset($object->status_verifikasi) ? (string) $object->status_verifikasi : null,
                isset($object->status_barang_temuan) ? (string) $object->status_barang_temuan : null
            );
        }

        return (string) ($object->status ?? '');
    }

    /**
     * Claim status resolution
     *
     * @return array{key: string, label: string, color: string, semantic: string, legacyClass: string}
     */
    public static function resolveClaimStatus(string $status): array
    {
        $normalized = ClaimStatusPresenter::normalize($status);
        $label = ClaimStatusPresenter::label($normalized);

        return match ($normalized) {
            'selesai' => [
                'key' => 'selesai',
                'label' => $label,
                'color' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'semantic' => 'success',
                'legacyClass' => 'status-selesai',
            ],
            'ditolak' => [
                'key' => 'ditolak',
                'label' => $label,
                'color' => 'bg-rose-50 text-rose-700 border border-rose-200',
                'semantic' => 'danger',
                'legacyClass' => 'status-ditolak',
            ],
            'disetujui' => [
                'key' => 'disetujui',
                'label' => $label,
                'color' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'semantic' => 'warning',
                'legacyClass' => 'status-diproses',
            ],
            default => [
                'key' => 'menunggu',
                'label' => $label,
                'color' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'semantic' => 'info',
                'legacyClass' => 'status-dalam_peninjauan',
            ],
        };
    }

    /**
     * Report status resolution
     *
     * @return array{key: string, label: string, color: string, semantic: string, legacyClass: string}
     */
    public static function resolveReportStatus(string $status): array
    {
        $key = ReportStatusPresenter::key($status);
        $label = ReportStatusPresenter::label($key);
        $legacyClass = ReportStatusPresenter::cssClass($key);

        return match ($key) {
            WorkflowStatus::REPORT_APPROVED, WorkflowStatus::REPORT_COMPLETED => [
                'key' => $key,
                'label' => $label,
                'color' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'semantic' => 'success',
                'legacyClass' => $legacyClass,
            ],
            WorkflowStatus::REPORT_REJECTED => [
                'key' => $key,
                'label' => $label,
                'color' => 'bg-rose-50 text-rose-700 border border-rose-200',
                'semantic' => 'danger',
                'legacyClass' => $legacyClass,
            ],
            WorkflowStatus::REPORT_MATCHED => [
                'key' => $key,
                'label' => $label,
                'color' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'semantic' => 'info',
                'legacyClass' => $legacyClass,
            ],
            WorkflowStatus::REPORT_CLAIMED => [
                'key' => $key,
                'label' => $label,
                'color' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'semantic' => 'warning',
                'legacyClass' => $legacyClass,
            ],
            default => [
                'key' => $key,
                'label' => $label,
                'color' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'semantic' => 'warning',
                'legacyClass' => $legacyClass,
            ],
        };
    }

    /**
     * Found item status resolution
     *
     * @return array{key: string, label: string, color: string, semantic: string, legacyClass: string}
     */
    public static function resolveFoundItemStatus(string $status): array
    {
        $clean = strtolower(trim($status));

        return match ($clean) {
            WorkflowStatus::FOUND_AVAILABLE, 'tersedia' => [
                'key' => 'tersedia',
                'label' => 'TERSEDIA',
                'color' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'semantic' => 'info',
                'legacyClass' => 'status-dalam_peninjauan',
            ],
            WorkflowStatus::FOUND_CLAIM_IN_PROGRESS, 'dalam_proses_klaim' => [
                'key' => 'dalam_proses_klaim',
                'label' => 'DALAM PROSES KLAIM',
                'color' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'semantic' => 'warning',
                'legacyClass' => 'status-diproses',
            ],
            WorkflowStatus::FOUND_CLAIMED, 'sudah_diklaim' => [
                'key' => 'sudah_diklaim',
                'label' => 'SUDAH DIKLAIM',
                'color' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'semantic' => 'success',
                'legacyClass' => 'status-selesai',
            ],
            WorkflowStatus::FOUND_RETURNED, 'sudah_dikembalikan', 'selesai' => [
                'key' => 'selesai',
                'label' => 'SELESAI',
                'color' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'semantic' => 'success',
                'legacyClass' => 'status-selesai',
            ],
            default => [
                'key' => $clean ?: 'unknown',
                'label' => strtoupper(str_replace('_', ' ', $clean ?: 'TIDAK DIKETAHUI')),
                'color' => 'bg-slate-50 text-slate-700 border border-slate-200',
                'semantic' => 'neutral',
                'legacyClass' => 'status-diproses',
            ],
        };
    }

    /**
     * Super admin manager verification status resolution
     *
     * @return array{key: string, label: string, color: string, semantic: string, legacyClass: string}
     */
    public static function resolveAdminStatus(string $status): array
    {
        $key = AdminVerificationStatusPresenter::key($status);
        $label = AdminVerificationStatusPresenter::label($key);
        $legacyClass = AdminVerificationStatusPresenter::badgeClass($key);

        return match ($key) {
            'active' => [
                'key' => 'active',
                'label' => $label,
                'color' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'semantic' => 'success',
                'legacyClass' => $legacyClass,
            ],
            'rejected' => [
                'key' => 'rejected',
                'label' => $label,
                'color' => 'bg-rose-50 text-rose-700 border border-rose-200',
                'semantic' => 'danger',
                'legacyClass' => $legacyClass,
            ],
            'inactive' => [
                'key' => 'inactive',
                'label' => $label,
                'color' => 'bg-slate-50 text-slate-700 border border-slate-200',
                'semantic' => 'neutral',
                'legacyClass' => $legacyClass,
            ],
            default => [
                'key' => 'pending',
                'label' => $label,
                'color' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'semantic' => 'warning',
                'legacyClass' => $legacyClass,
            ],
        };
    }

    /**
     * Log / Notification status resolution
     *
     * @return array{key: string, label: string, color: string, semantic: string, legacyClass: string}
     */
    public static function resolveLogStatus(string $status): array
    {
        $clean = strtolower(trim($status));
        $isRead = in_array($clean, ['read', 'sudah_dibaca', '1', 'true'], true);

        return $isRead ? [
            'key' => 'read',
            'label' => 'Sudah Dibaca',
            'color' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            'semantic' => 'success',
            'legacyClass' => 'status-selesai',
        ] : [
            'key' => 'unread',
            'label' => 'Belum Dibaca',
            'color' => 'bg-blue-50 text-blue-700 border border-blue-200',
            'semantic' => 'info',
            'legacyClass' => 'status-dalam_peninjauan',
        ];
    }

    /**
     * General status resolution fallback
     *
     * @return array{key: string, label: string, color: string, semantic: string, legacyClass: string}
     */
    public static function resolveGeneralStatus(string $status): array
    {
        $clean = strtolower(trim($status));

        if (in_array($clean, ['selesai', 'completed', 'success', 'approved', 'disetujui', 'aktif', 'active'], true)) {
            return [
                'key' => $clean,
                'label' => strtoupper(str_replace('_', ' ', $clean)),
                'color' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'semantic' => 'success',
                'legacyClass' => 'status-selesai',
            ];
        }

        if (in_array($clean, ['ditolak', 'rejected', 'failed', 'danger', 'batal', 'cancelled'], true)) {
            return [
                'key' => $clean,
                'label' => strtoupper(str_replace('_', ' ', $clean)),
                'color' => 'bg-rose-50 text-rose-700 border border-rose-200',
                'semantic' => 'danger',
                'legacyClass' => 'status-ditolak',
            ];
        }

        if (in_array($clean, ['menunggu', 'pending', 'review', 'warning', 'diproses', 'dalam_proses'], true)) {
            return [
                'key' => $clean,
                'label' => strtoupper(str_replace('_', ' ', $clean)),
                'color' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'semantic' => 'warning',
                'legacyClass' => 'status-diproses',
            ];
        }

        return [
            'key' => $clean ?: 'default',
            'label' => strtoupper(str_replace('_', ' ', $clean ?: '-')),
            'color' => 'bg-blue-50 text-blue-700 border border-blue-200',
            'semantic' => 'info',
            'legacyClass' => 'status-dalam_peninjauan',
        ];
    }

    /**
     * Standardized size variants
     */
    public static function sizeClasses(string $size = 'sm'): string
    {
        return match ($size) {
            'md' => 'px-3.5 py-1.5 text-xs sm:text-sm',
            'lg' => 'px-4 py-2 text-sm sm:text-base',
            default => 'px-3 py-1 text-xs',
        };
    }

    /**
     * Build the complete class list for badge rendering
     */
    public static function badgeClasses(mixed $status, ?string $type = null, string $size = 'sm', ?string $extraClasses = null): string
    {
        $resolved = self::resolve($status, $type);
        $base = 'inline-flex items-center justify-center font-semibold rounded-full whitespace-nowrap';
        $sizeClass = self::sizeClasses($size);
        $color = $resolved['color'];
        $legacy = $resolved['legacyClass'];

        $classes = "{$base} {$sizeClass} {$color} status-chip {$legacy}";

        if (! empty($extraClasses)) {
            $classes .= " {$extraClasses}";
        }

        return trim($classes);
    }
}
