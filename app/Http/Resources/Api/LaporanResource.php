<?php

namespace App\Http\Resources\Api;

use App\Http\Resources\Api\Concerns\FormatsApiValues;
use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaporanResource extends JsonResource
{
    use FormatsApiValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof LaporanBarangHilang) {
            return $this->lostReport();
        }

        if ($this->resource instanceof Barang) {
            return $this->foundReport($request);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function lostReport(): array
    {
        return [
            'id' => (int) $this->id,
            'type' => 'hilang',
            'nama_barang' => (string) $this->nama_barang,
            'kategori' => (string) ($this->kategori?->nama_kategori ?? $this->kategori_barang ?? ''),
            'kategori_id' => $this->kategori_id !== null ? (int) $this->kategori_id : null,
            'wilayah' => $this->region?->nama_wilayah,
            'wilayah_id' => $this->region_id !== null ? (int) $this->region_id : null,
            'lokasi' => (string) $this->lokasi_hilang,
            'deskripsi' => $this->keterangan,
            'status' => $this->reportStatusForMobile($this->status_laporan),
            'tanggal' => $this->formatDateValue($this->tanggal_hilang),
            'image_url' => $this->publicImageUrl($this->foto_barang),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function foundReport(Request $request): array
    {
        $claimState = $this->foundReportClaimState($request);

        return [
            'id' => (int) $this->id,
            'type' => 'temuan',
            'nama_barang' => (string) $this->nama_barang,
            'kategori' => $this->kategori?->nama_kategori,
            'kategori_id' => $this->kategori_id !== null ? (int) $this->kategori_id : null,
            'wilayah' => $this->region?->nama_wilayah,
            'wilayah_id' => $this->region_id !== null ? (int) $this->region_id : null,
            'lokasi' => (string) $this->lokasi_ditemukan,
            'deskripsi' => $this->deskripsi,
            'status' => $this->reportStatusForMobile($this->status_laporan),
            'status_barang' => $this->status_barang,
            'tanggal' => $this->formatDateValue($this->tanggal_ditemukan),
            'image_url' => $this->publicImageUrl($this->foto_barang),
            'is_owner' => $claimState['is_owner'],
            'claimable' => $claimState['claimable'],
            'claim_block_reason' => $claimState['claim_block_reason'],
        ];
    }

    /**
     * @return array{is_owner: bool, claimable: bool, claim_block_reason: string|null}
     */
    private function foundReportClaimState(Request $request): array
    {
        $userId = $request->user()?->id;
        $isOwner = $userId !== null && (int) $this->user_id === (int) $userId;

        if ($userId === null) {
            return [
                'is_owner' => false,
                'claimable' => false,
                'claim_block_reason' => 'Login diperlukan untuk klaim barang.',
            ];
        }

        if ($isOwner) {
            return [
                'is_owner' => true,
                'claimable' => false,
                'claim_block_reason' => 'Tidak bisa mengklaim barang temuan milik sendiri.',
            ];
        }

        if ($this->hasClaimFromUser((int) $userId)) {
            return [
                'is_owner' => false,
                'claimable' => false,
                'claim_block_reason' => 'Klaim sudah pernah diajukan.',
            ];
        }

        if ((string) $this->status_barang !== WorkflowStatus::FOUND_AVAILABLE) {
            return [
                'is_owner' => false,
                'claimable' => false,
                'claim_block_reason' => 'Barang sudah diklaim atau sedang diproses.',
            ];
        }

        if (! in_array((string) ($this->status_laporan ?? ''), [
            WorkflowStatus::REPORT_APPROVED,
            WorkflowStatus::REPORT_MATCHED,
        ], true)) {
            return [
                'is_owner' => false,
                'claimable' => false,
                'claim_block_reason' => 'Barang temuan belum bisa diklaim.',
            ];
        }

        if (! $this->admin_id) {
            return [
                'is_owner' => false,
                'claimable' => false,
                'claim_block_reason' => 'Barang belum memiliki pengelola untuk memproses klaim.',
            ];
        }

        if ($this->hasActiveClaim()) {
            return [
                'is_owner' => false,
                'claimable' => false,
                'claim_block_reason' => 'Barang sudah diklaim atau sedang diproses.',
            ];
        }

        return [
            'is_owner' => false,
            'claimable' => true,
            'claim_block_reason' => null,
        ];
    }

    private function hasClaimFromUser(int $userId): bool
    {
        return Klaim::query()
            ->where('barang_id', (int) $this->id)
            ->where('user_id', $userId)
            ->activeForSubmission()
            ->exists();
    }

    private function hasActiveClaim(): bool
    {
        return Klaim::query()
            ->where('barang_id', (int) $this->id)
            ->where(function ($query): void {
                $query
                    ->activeForSubmission()
                    ->orWhere('status_verifikasi', WorkflowStatus::CLAIM_COMPLETED);
            })
            ->exists();
    }
}
