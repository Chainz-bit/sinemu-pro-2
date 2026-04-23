<?php

namespace App\Support;

final class WorkflowStatus
{
    public const REPORT_SUBMITTED = 'submitted';
    public const REPORT_APPROVED = 'approved';
    public const REPORT_REJECTED = 'rejected';
    public const REPORT_MATCHED = 'matched';
    public const REPORT_CLAIMED = 'claimed';
    public const REPORT_COMPLETED = 'completed';

    public const MATCH_CONFIRMED = 'confirmed';
    public const MATCH_CLAIM_IN_PROGRESS = 'claim_in_progress';
    public const MATCH_CLAIM_APPROVED = 'claim_approved';
    public const MATCH_CLAIM_REJECTED = 'claim_rejected';
    public const MATCH_COMPLETED = 'completed';
    public const MATCH_CANCELLED = 'cancelled';

    public const CLAIM_SUBMITTED = 'submitted';
    public const CLAIM_UNDER_REVIEW = 'under_review';
    public const CLAIM_APPROVED = 'approved';
    public const CLAIM_REJECTED = 'rejected';
    public const CLAIM_COMPLETED = 'completed';

    public const CLAIM_LEGACY_PENDING = 'pending';
    public const CLAIM_LEGACY_APPROVED = 'disetujui';
    public const CLAIM_LEGACY_REJECTED = 'ditolak';

    public const FOUND_AVAILABLE = 'tersedia';
    public const FOUND_CLAIM_IN_PROGRESS = 'dalam_proses_klaim';
    public const FOUND_CLAIMED = 'sudah_diklaim';
    public const FOUND_RETURNED = 'sudah_dikembalikan';

    /**
     * @return array<int,string>
     */
    public static function reportStatuses(): array
    {
        return [
            self::REPORT_SUBMITTED,
            self::REPORT_APPROVED,
            self::REPORT_REJECTED,
            self::REPORT_MATCHED,
            self::REPORT_CLAIMED,
            self::REPORT_COMPLETED,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function foundItemStatuses(): array
    {
        return [
            self::FOUND_AVAILABLE,
            self::FOUND_CLAIM_IN_PROGRESS,
            self::FOUND_CLAIMED,
            self::FOUND_RETURNED,
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function legacyClaimStatuses(): array
    {
        return [
            self::CLAIM_LEGACY_PENDING,
            self::CLAIM_LEGACY_APPROVED,
            self::CLAIM_LEGACY_REJECTED,
        ];
    }

    /**
     * Status pencocokan yang masih memblokir pasangan lain untuk laporan/barang yang sama.
     *
     * @return array<int,string>
     */
    public static function blockingMatchStatuses(): array
    {
        return [
            self::MATCH_CONFIRMED,
            self::MATCH_CLAIM_IN_PROGRESS,
            self::MATCH_CLAIM_APPROVED,
        ];
    }
}
