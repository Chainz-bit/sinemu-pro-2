<?php

namespace App\Services\Home;

use App\Models\Barang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Carbon;

class HomeFoundDetailService
{
    public function __construct(
        private readonly HomeMediaAssetService $mediaAssetService
    ) {
    }

    /**
     * @return array{pageTitle:string,detail:object}
     */
    public function build(Barang $barang): array
    {
        $validStatuses = [
            WorkflowStatus::REPORT_APPROVED,
            WorkflowStatus::REPORT_MATCHED,
            WorkflowStatus::REPORT_CLAIMED,
            WorkflowStatus::REPORT_COMPLETED,
        ];
        $hasValidStatus = in_array((string) ($barang->status_laporan ?? ''), $validStatuses, true);
        $isPublished = (bool) ($barang->tampil_di_home ?? false);
        abort_unless($hasValidStatus || $isPublished, 404);

        $statusBarang = (string) ($barang->status_barang ?? '');
        $statusMeta = match ($statusBarang) {
            'dalam_proses_klaim' => [
                'label' => 'Sedang Diproses Klaim',
                'class' => 'is-in-progress',
                'claimable' => false,
                'subtitle' => 'Barang ini sedang dalam proses verifikasi klaim. Pengajuan klaim baru tidak dapat dilakukan saat ini.',
            ],
            'sudah_diklaim' => [
                'label' => 'Sudah Diklaim',
                'class' => 'is-claimed',
                'claimable' => false,
                'subtitle' => 'Barang ini sudah melalui proses klaim dan tidak tersedia untuk pengajuan klaim baru.',
            ],
            'sudah_dikembalikan' => [
                'label' => 'Sudah Dikembalikan',
                'class' => 'is-returned',
                'claimable' => false,
                'subtitle' => 'Barang ini sudah dikembalikan kepada pemilik dan tidak tersedia untuk klaim baru.',
            ],
            default => [
                'label' => 'Tersedia untuk Diklaim',
                'class' => 'is-found',
                'claimable' => true,
                'subtitle' => 'Detail laporan barang temuan untuk membantu pengguna memahami informasi sebelum tindak lanjut.',
            ],
        };

        $claimActionUrl = route('user.claims.create', ['barang_id' => $barang->id]);
        $claimActionLabel = 'Ajukan Klaim';
        if ($statusBarang === 'dalam_proses_klaim') {
            $claimActionUrl = route('user.claim-history');
            $claimActionLabel = 'Lihat Status Klaim';
        } elseif ($statusBarang === 'sudah_diklaim') {
            $claimActionUrl = route('user.claim-history');
            $claimActionLabel = 'Lihat Instruksi Pengambilan';
        } elseif ($statusBarang === 'sudah_dikembalikan') {
            $claimActionUrl = route('user.claim-history');
            $claimActionLabel = 'Lihat Riwayat Klaim';
        }

        $penanggungJawab = $barang->admin?->instansi ?? $barang->admin?->nama ?? 'Admin';
        $detail = (object) [
            'id' => (int) $barang->id,
            'type' => 'temuan',
            'title' => (string) $barang->nama_barang,
            'category' => ucwords(strtolower((string) ($barang->kategori?->nama_kategori ?? 'Umum'))),
            'location' => (string) $barang->lokasi_ditemukan,
            'date_label' => $barang->tanggal_ditemukan
                ? Carbon::parse((string) $barang->tanggal_ditemukan)->translatedFormat('d F Y')
                : '-',
            'status_label' => $statusMeta['label'],
            'status_class' => $statusMeta['class'],
            'description' => trim((string) ($barang->deskripsi ?? '')) !== ''
                ? (string) $barang->deskripsi
                : 'Belum ada deskripsi tambahan.',
            'reporter' => $penanggungJawab,
            'image_url' => $this->mediaAssetService->resolveItemImageUrl((string) ($barang->foto_barang ?? ''), 'barang-temuan'),
            'subtitle' => $statusMeta['subtitle'],
            'is_claimable' => $statusMeta['claimable'],
            'claim_action_url' => $claimActionUrl,
            'claim_action_label' => $claimActionLabel,
            'preclaim_note' => 'Kecocokan barang tidak otomatis membuktikan kepemilikan. Anda wajib mengajukan klaim dengan bukti kepemilikan untuk diverifikasi admin.',
        ];

        return [
            'pageTitle' => 'Detail Barang Temuan - SiNemu',
            'detail' => $detail,
        ];
    }
}
