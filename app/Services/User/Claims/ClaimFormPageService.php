<?php

namespace App\Services\User\Claims;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Collection;

class ClaimFormPageService
{
    /**
     * @return array{foundItems:Collection<int,Barang>,claimableLostReports:Collection<int,LaporanBarangHilang>,selectedBarangId:int|null}
     */
    public function build(int $userId, ?int $requestedBarangId = null): array
    {
        $claimableLostReports = $this->getClaimableLostReports($userId);
        $foundItems = $this->getClaimableFoundItems($userId);

        $selectedBarangId = null;
        if (!is_null($requestedBarangId) && $foundItems->contains(fn (Barang $barang) => (int) $barang->id === $requestedBarangId)) {
            $selectedBarangId = $requestedBarangId;
        } elseif ($foundItems->isNotEmpty()) {
            $selectedBarangId = (int) $foundItems->first()->id;
        }

        return [
            'foundItems' => $foundItems,
            'claimableLostReports' => $claimableLostReports,
            'selectedBarangId' => $selectedBarangId,
        ];
    }

    /**
     * @return Collection<int,LaporanBarangHilang>
     */
    private function getClaimableLostReports(int $userId): Collection
    {
        if ($userId <= 0) {
            return collect();
        }

        $query = LaporanBarangHilang::query()
            ->where('user_id', $userId)
            ->where('sumber_laporan', 'lapor_hilang')
            ->whereIn('status_laporan', [
                WorkflowStatus::REPORT_APPROVED,
                WorkflowStatus::REPORT_MATCHED,
                WorkflowStatus::REPORT_CLAIMED,
            ])
            ->select([
                'id',
                'nama_barang',
                'lokasi_hilang',
                'tanggal_hilang',
                'kontak_pelapor',
                'bukti_kepemilikan',
                'ciri_khusus',
                'detail_lokasi_hilang',
                'waktu_hilang',
            ])
            ->orderByDesc('tanggal_hilang')
            ->orderByDesc('updated_at');

        return $query->get();
    }

    /**
     * @return Collection<int,Barang>
     */
    private function getClaimableFoundItems(int $userId): Collection
    {
        if ($userId <= 0) {
            return collect();
        }

        $query = Barang::query()
            ->with('kategori:id,nama_kategori')
            ->where('status_barang', 'tersedia')
            ->whereIn('status_laporan', [
                WorkflowStatus::REPORT_APPROVED,
                WorkflowStatus::REPORT_MATCHED,
                WorkflowStatus::REPORT_CLAIMED,
            ])
            ->where(function ($query) use ($userId): void {
                $query
                    ->whereNull('user_id')
                    ->orWhere('user_id', '!=', $userId);
            })
            ->whereHas('admin', function ($query): void {
                $query
                    ->where('status_verifikasi', Admin::STATUS_ACTIVE)
                    ->whereColumn('admins.region_id', 'barangs.region_id');
            })
            ->whereDoesntHave('klaims', function ($query): void {
                $query->where(function ($claimQuery): void {
                    $claimQuery
                        ->activeForSubmission()
                        ->orWhere('status_verifikasi', WorkflowStatus::CLAIM_COMPLETED);
                });
            })
            ->select([
                'id',
                'kategori_id',
                'nama_barang',
                'lokasi_ditemukan',
                'tanggal_ditemukan',
                'status_barang',
            ])
            ->orderByDesc('updated_at');

        return $query->get();
    }
}
