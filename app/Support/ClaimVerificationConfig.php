<?php

namespace App\Support;

class ClaimVerificationConfig
{
    public const MINIMUM_APPROVAL_SCORE = 75;

    public const MINIMUM_AI_SCORE = 75;

    public const MINIMUM_OVERRIDE_NOTE_LENGTH = 15;

    /**
     * Bobot penilaian verifikasi klaim.
     * Total bobot = 100 poin.
     *
     * @var array<string,int>
     */
    public const CHECKLIST_WEIGHTS = [
        'identitas_pelapor_valid' => 20,
        'detail_barang_valid' => 25,
        'kronologi_valid' => 20,
        'bukti_visual_valid' => 20,
        'kecocokan_data_laporan' => 15,
    ];

    /**
     * Poin checklist yang wajib bernilai true (kritikal).
     *
     * @var array<int,string>
     */
    public const CRITICAL_CHECKLIST_KEYS = [
        'detail_barang_valid',
        'kronologi_valid',
        'bukti_visual_valid',
    ];

    /**
     * Seluruh kunci poin verifikasi checklist.
     *
     * @var array<int,string>
     */
    public const ALL_CHECKLIST_KEYS = [
        'identitas_pelapor_valid',
        'detail_barang_valid',
        'kronologi_valid',
        'bukti_visual_valid',
        'kecocokan_data_laporan',
    ];

    /**
     * Hitung total skor dari data checklist yang valid.
     *
     * @param  array<string,mixed>  $checklist
     */
    public static function calculateScore(array $checklist): int
    {
        $score = 0;
        foreach (self::CHECKLIST_WEIGHTS as $key => $weight) {
            if (! empty($checklist[$key])) {
                $score += $weight;
            }
        }

        return $score;
    }

    /**
     * Cek apakah seluruh 5 poin checklist bernilai benar (Ya / 1).
     *
     * @param  array<string,mixed>  $checklist
     */
    public static function areAllChecksPassed(array $checklist): bool
    {
        foreach (self::ALL_CHECKLIST_KEYS as $key) {
            $val = $checklist[$key] ?? 0;
            if ((int) $val !== 1 && $val !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cek apakah poin-poin checklist kritikal lolos validasi.
     *
     * @param  array<string,mixed>  $checklist
     */
    public static function areCriticalChecksPassed(array $checklist): bool
    {
        foreach (self::CRITICAL_CHECKLIST_KEYS as $key) {
            $val = $checklist[$key] ?? 0;
            if ((int) $val !== 1 && $val !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cek apakah skor memenuhi ambang batas persetujuan.
     */
    public static function isScoreSufficient(int $score): bool
    {
        return $score >= self::MINIMUM_APPROVAL_SCORE;
    }

    /**
     * Cek apakah skor AI memenuhi ambang batas jika ada.
     */
    public static function canApproveAiScore(?int $aiScore): bool
    {
        if ($aiScore === null) {
            return true;
        }

        return $aiScore >= self::MINIMUM_AI_SCORE;
    }

    /**
     * Evaluasi kelayakan manual override oleh pengelola saat skor AI di bawah ambang batas.
     */
    public static function isEligibleForManualOverride(?int $aiScore, ?string $notes): bool
    {
        if (self::canApproveAiScore($aiScore)) {
            return true;
        }

        return mb_strlen(trim((string) $notes)) >= self::MINIMUM_OVERRIDE_NOTE_LENGTH;
    }
}
