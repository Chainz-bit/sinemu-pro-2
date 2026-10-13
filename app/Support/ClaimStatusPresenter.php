<?php

namespace App\Support;

final class ClaimStatusPresenter
{
    public static function key(?string $statusKlaim, ?string $statusVerifikasi = null, ?string $statusBarang = null): string
    {
        $statusKlaim = (string) ($statusKlaim ?? '');
        $statusVerifikasi = (string) ($statusVerifikasi ?? '');
        $statusBarang = (string) ($statusBarang ?? '');

        if ($statusVerifikasi === WorkflowStatus::CLAIM_COMPLETED || $statusBarang === WorkflowStatus::FOUND_RETURNED || $statusKlaim === 'selesai') {
            return 'selesai';
        }

        if ($statusVerifikasi === WorkflowStatus::CLAIM_REJECTED || $statusKlaim === WorkflowStatus::CLAIM_LEGACY_REJECTED || $statusKlaim === 'ditolak') {
            return 'ditolak';
        }

        if ($statusVerifikasi === WorkflowStatus::CLAIM_APPROVED || $statusKlaim === WorkflowStatus::CLAIM_LEGACY_APPROVED || $statusKlaim === 'disetujui' || $statusKlaim === 'terverifikasi') {
            return 'disetujui';
        }

        return 'menunggu';
    }

    public static function normalize(?string $status): string
    {
        $clean = strtolower(trim((string) ($status ?? '')));

        if ($clean === WorkflowStatus::CLAIM_COMPLETED || $clean === 'selesai' || $clean === 'completed' || $clean === 'status-selesai') {
            return 'selesai';
        }

        if ($clean === WorkflowStatus::CLAIM_REJECTED || $clean === WorkflowStatus::CLAIM_LEGACY_REJECTED || $clean === 'ditolak' || $clean === 'rejected' || $clean === 'status-ditolak') {
            return 'ditolak';
        }

        if ($clean === WorkflowStatus::CLAIM_APPROVED || $clean === WorkflowStatus::CLAIM_LEGACY_APPROVED || $clean === 'disetujui' || $clean === 'terverifikasi' || $clean === 'approved' || $clean === 'diproses' || $clean === 'status-diproses') {
            return 'disetujui';
        }

        return 'menunggu';
    }

    public static function label(string $key): string
    {
        return match ($key) {
            'selesai' => 'SELESAI',
            'ditolak' => 'DITOLAK',
            'disetujui' => 'TERVERIFIKASI',
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
