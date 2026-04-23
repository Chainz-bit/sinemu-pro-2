<?php

namespace App\Services\Home;

use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Carbon;

class HomeLostDetailService
{
    public function __construct(
        private readonly HomeMediaAssetService $mediaAssetService
    ) {
    }

    /**
     * @return array{pageTitle:string,detail:object}
     */
    public function build(LaporanBarangHilang $laporanBarangHilang): array
    {
        $validStatuses = [
            WorkflowStatus::REPORT_APPROVED,
            WorkflowStatus::REPORT_MATCHED,
            WorkflowStatus::REPORT_CLAIMED,
            WorkflowStatus::REPORT_COMPLETED,
        ];
        $hasValidStatus = in_array((string) ($laporanBarangHilang->status_laporan ?? ''), $validStatuses, true);
        $isPublished = (bool) ($laporanBarangHilang->tampil_di_home ?? false);
        abort_unless($hasValidStatus || $isPublished, 404);

        [$lostStatusLabel, $lostStatusClass] = match ((string) ($laporanBarangHilang->status_laporan ?? WorkflowStatus::REPORT_SUBMITTED)) {
            WorkflowStatus::REPORT_APPROVED => ['Terverifikasi', 'is-in-progress'],
            WorkflowStatus::REPORT_MATCHED => ['Sudah Dicocokkan', 'is-found'],
            WorkflowStatus::REPORT_CLAIMED => ['Sedang Diproses Klaim', 'is-in-progress'],
            WorkflowStatus::REPORT_COMPLETED => ['Selesai', 'is-returned'],
            WorkflowStatus::REPORT_REJECTED => ['Ditolak Admin', 'is-returned'],
            default => ['Menunggu Verifikasi', 'is-in-progress'],
        };

        $pelapor = $laporanBarangHilang->user?->nama ?? $laporanBarangHilang->user?->name ?? 'Pengguna';
        $detail = (object) [
            'type' => 'hilang',
            'title' => (string) $laporanBarangHilang->nama_barang,
            'category' => 'Umum',
            'location' => (string) $laporanBarangHilang->lokasi_hilang,
            'date_label' => $laporanBarangHilang->tanggal_hilang
                ? Carbon::parse((string) $laporanBarangHilang->tanggal_hilang)->translatedFormat('d F Y')
                : '-',
            'status_label' => $lostStatusLabel,
            'status_class' => $lostStatusClass,
            'description' => trim((string) ($laporanBarangHilang->keterangan ?? '')) !== ''
                ? (string) $laporanBarangHilang->keterangan
                : 'Belum ada deskripsi tambahan dari pelapor.',
            'reporter' => $pelapor,
            'image_url' => $this->mediaAssetService->resolveItemImageUrl((string) ($laporanBarangHilang->foto_barang ?? ''), 'barang-hilang'),
        ];

        return [
            'pageTitle' => 'Detail Barang Hilang - SiNemu',
            'detail' => $detail,
        ];
    }
}
