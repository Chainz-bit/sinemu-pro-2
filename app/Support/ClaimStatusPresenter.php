<?php

namespace App\Support;

final class ClaimStatusPresenter
{
    public static function key(?string $statusKlaim, ?string $statusVerifikasi = null, ?string $statusBarang = null): string
    {
        $statusKlaim = (string) ($statusKlaim ?? '');
        $statusVerifikasi = (string) ($statusVerifikasi ?? '');
        $statusBarang = (string) ($statusBarang ?? '');

        if ($statusVerifikasi === WorkflowStatus::CLAIM_COMPLETED || $statusBarang === WorkflowStatus::FOUND_RETURNED) {
            return 'selesai';
        }

        if ($statusVerifikasi === WorkflowStatus::CLAIM_REJECTED || $statusKlaim === WorkflowStatus::CLAIM_LEGACY_REJECTED) {
            return 'ditolak';
        }

        if ($statusVerifikasi === WorkflowStatus::CLAIM_APPROVED || $statusKlaim === WorkflowStatus::CLAIM_LEGACY_APPROVED) {
            return 'disetujui';
        }

        return 'menunggu';
    }

    public static function label(string $key): string
    {
        return match ($key) {
            'selesai' => 'SELESAI',
            'ditolak' => 'DITOLAK',
            'disetujui' => 'DISETUJUI',
            default => 'MENUNGGU VERIFIKASI',
        };
    }

    public static function cssClass(string $key): string
    {
        return match ($key) {
            'selesai' => 'status-selesai',
            'ditolak' => 'status-ditolak',
            'disetujui' => 'status-diproses',
            default => 'status-dalam_peninjauan',
        };
    }
}
