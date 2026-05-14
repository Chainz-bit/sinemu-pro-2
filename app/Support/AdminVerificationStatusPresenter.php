<?php

namespace App\Support;

class AdminVerificationStatusPresenter
{
    public static function key(?string $status): string
    {
        return match (trim((string) $status)) {
            'active' => 'active',
            'rejected' => 'rejected',
            'inactive' => 'inactive',
            default => 'pending',
        };
    }

    public static function label(?string $status): string
    {
        return match (self::key($status)) {
            'active' => 'Aktif',
            'rejected' => 'Ditolak',
            'inactive' => 'Nonaktif',
            default => 'Menunggu',
        };
    }

    public static function badgeClass(?string $status): string
    {
        return match (self::key($status)) {
            'active' => 'status-selesai',
            'rejected' => 'status-ditolak',
            'inactive' => 'status-dibatalkan',
            default => 'status-diproses',
        };
    }

    public static function cardClass(?string $status): string
    {
        return match (self::key($status)) {
            'active' => 'stat-card-found',
            'rejected' => 'stat-card-lost',
            'inactive' => 'stat-card-muted',
            default => 'stat-card-claim',
        };
    }

    public static function description(?string $status): string
    {
        return match (self::key($status)) {
            'active' => \App\Support\RoleLabels::manager() . ' sudah bisa mengakses dashboard dan mengelola laporan.',
            'rejected' => 'Pendaftaran ditolak dan menunggu perbaikan data oleh pendaftar.',
            'inactive' => 'Akun dinonaktifkan dan tidak dapat mengakses dashboard pengelola barang.',
            default => \App\Support\RoleLabels::manager() . ' menunggu tinjauan dan keputusan super admin.',
        };
    }
}
