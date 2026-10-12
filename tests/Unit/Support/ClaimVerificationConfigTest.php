<?php

namespace Tests\Unit\Support;

use App\Support\ClaimVerificationConfig;
use PHPUnit\Framework\TestCase;

class ClaimVerificationConfigTest extends TestCase
{
    public function test_threshold_constants_are_set_to_75(): void
    {
        $this->assertSame(75, ClaimVerificationConfig::MINIMUM_APPROVAL_SCORE);
        $this->assertSame(75, ClaimVerificationConfig::MINIMUM_AI_SCORE);
    }

    public function test_calculate_score_returns_zero_when_empty(): void
    {
        $this->assertSame(0, ClaimVerificationConfig::calculateScore([]));
    }

    public function test_calculate_score_sums_all_weights_to_100(): void
    {
        $fullChecklist = [
            'identitas_pelapor_valid' => 1,
            'detail_barang_valid' => 1,
            'kronologi_valid' => 1,
            'bukti_visual_valid' => 1,
            'kecocokan_data_laporan' => 1,
        ];

        $this->assertSame(100, ClaimVerificationConfig::calculateScore($fullChecklist));
    }

    public function test_calculate_score_with_partial_checklist(): void
    {
        $partial = [
            'identitas_pelapor_valid' => 1, // 20
            'detail_barang_valid' => 1,    // 25
            'kronologi_valid' => 0,        // 0
            'bukti_visual_valid' => 1,     // 20
            'kecocokan_data_laporan' => 0, // 0
        ];

        $this->assertSame(65, ClaimVerificationConfig::calculateScore($partial));
    }

    public function test_is_score_sufficient_evaluates_threshold_75(): void
    {
        $this->assertTrue(ClaimVerificationConfig::isScoreSufficient(75));
        $this->assertTrue(ClaimVerificationConfig::isScoreSufficient(100));
        $this->assertFalse(ClaimVerificationConfig::isScoreSufficient(74));
        $this->assertFalse(ClaimVerificationConfig::isScoreSufficient(0));
    }

    public function test_can_approve_ai_score(): void
    {
        // Null means no AI score was generated (e.g. manual verification allowed)
        $this->assertTrue(ClaimVerificationConfig::canApproveAiScore(null));
        $this->assertTrue(ClaimVerificationConfig::canApproveAiScore(75));
        $this->assertTrue(ClaimVerificationConfig::canApproveAiScore(90));
        $this->assertFalse(ClaimVerificationConfig::canApproveAiScore(74));
        $this->assertFalse(ClaimVerificationConfig::canApproveAiScore(50));
    }

    public function test_are_all_checks_passed_requires_all_five(): void
    {
        $allPassed = [
            'identitas_pelapor_valid' => 1,
            'detail_barang_valid' => 1,
            'kronologi_valid' => 1,
            'bukti_visual_valid' => 1,
            'kecocokan_data_laporan' => 1,
        ];
        $this->assertTrue(ClaimVerificationConfig::areAllChecksPassed($allPassed));

        $missingOne = $allPassed;
        $missingOne['kecocokan_data_laporan'] = 0;
        $this->assertFalse(ClaimVerificationConfig::areAllChecksPassed($missingOne));
    }

    public function test_are_critical_checks_passed(): void
    {
        // Only critical checks: detail_barang_valid, kronologi_valid, bukti_visual_valid
        $checks = [
            'identitas_pelapor_valid' => 0,
            'detail_barang_valid' => 1,
            'kronologi_valid' => 1,
            'bukti_visual_valid' => 1,
            'kecocokan_data_laporan' => 0,
        ];
        $this->assertTrue(ClaimVerificationConfig::areCriticalChecksPassed($checks));

        $checks['detail_barang_valid'] = 0;
        $this->assertFalse(ClaimVerificationConfig::areCriticalChecksPassed($checks));
    }

    public function test_minimum_override_note_length_is_set_to_15(): void
    {
        $this->assertSame(15, ClaimVerificationConfig::MINIMUM_OVERRIDE_NOTE_LENGTH);
    }

    public function test_is_eligible_for_manual_override_returns_true_when_ai_score_is_null(): void
    {
        // When AI has not run or is null, manual verification is allowed with or without notes
        $this->assertTrue(ClaimVerificationConfig::isEligibleForManualOverride(null, null));
        $this->assertTrue(ClaimVerificationConfig::isEligibleForManualOverride(null, ''));
    }

    public function test_is_eligible_for_manual_override_returns_true_when_ai_score_is_at_least_75(): void
    {
        $this->assertTrue(ClaimVerificationConfig::isEligibleForManualOverride(75, null));
        $this->assertTrue(ClaimVerificationConfig::isEligibleForManualOverride(85, ''));
        $this->assertTrue(ClaimVerificationConfig::isEligibleForManualOverride(100, 'Catatan'));
    }

    public function test_is_eligible_for_manual_override_when_ai_score_is_below_75(): void
    {
        // Score < 75 without notes or note < 15 characters must return false
        $this->assertFalse(ClaimVerificationConfig::isEligibleForManualOverride(50, null));
        $this->assertFalse(ClaimVerificationConfig::isEligibleForManualOverride(50, ''));
        $this->assertFalse(ClaimVerificationConfig::isEligibleForManualOverride(74, 'Kurang panjang')); // 14 chars

        // Score < 75 with note >= 15 characters must return true (valid override)
        $validNote = 'Verifikasi langsung di posko: nomor seri cocok 100%.'; // 53 chars
        $this->assertTrue(ClaimVerificationConfig::isEligibleForManualOverride(50, $validNote));
        $this->assertTrue(ClaimVerificationConfig::isEligibleForManualOverride(35, $validNote));

        // Exact boundary (15 characters)
        $exact15 = '123456789012345';
        $this->assertTrue(ClaimVerificationConfig::isEligibleForManualOverride(60, $exact15));

        // Exact boundary minus one (14 characters)
        $exact14 = '12345678901234';
        $this->assertFalse(ClaimVerificationConfig::isEligibleForManualOverride(60, $exact14));
    }
}
