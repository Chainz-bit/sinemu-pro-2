<?php

namespace App\Services\User\Claims;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Collection;

class ClaimFormPageService
{
    private const DROPDOWN_ITEMS_LIMIT = 50;

    /**
     * @return array{foundItems:Collection<int,Barang>,claimableLostReports:Collection<int,LaporanBarangHilang>,selectedBarangId:int|null}
     */
    public function build(int $userId, ?int $requestedBarangId = null): array
    {
        $claimableLostReports = $this->getClaimableLostReports($userId);

        // 1. Dukungan Targeted Item: jika request memiliki parameter barang_id spesifik,
        // cukup ambil 1 data barang temuan tersebut secara langsung tanpa me-load puluhan barang lain.
        if (!is_null($requestedBarangId) && $requestedBarangId > 0) {
            $targetedItem = $this->getTargetedFoundItem($userId, $requestedBarangId);
            if ($targetedItem !== null) {
                return [
                    'foundItems' => collect([$targetedItem]),
                    'claimableLostReports' => $claimableLostReports,
                    'selectedBarangId' => (int) $targetedItem->id,
                ];
            }
        }

        // 2. Scoping & Hard Limit jika form dibuka tanpa parameter barang_id spesifik
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
     * Ambil 1 barang temuan yang ditargetkan secara langsung berdasarkan ID.
     */
    private function getTargetedFoundItem(int $userId, int $barangId): ?Barang
    {
        return Barang::query()
            ->with('kategori:id,nama_kategori')
            ->where('id', $barangId)
            ->where('status_barang', WorkflowStatus::FOUND_AVAILABLE)
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
            ->select([
                'id',
                'nama_barang',
                'tanggal_ditemukan',
                'lokasi_ditemukan',
                'kategori_id',
                'region_id',
                'status_barang',
            ])
            ->first();
    }

    /**
     * @return Collection<int,LaporanBarangHilang>
     */
    private function getClaimableLostReports(int $userId): Collection
    {
        if ($userId <= 0) {
            return collect();
        }

        return LaporanBarangHilang::query()
            ->where('user_id', $userId)
            ->where('sumber_laporan', 'lapor_hilang')
            ->whereIn('status_laporan', [
                WorkflowStatus::REPORT_SUBMITTED,
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
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int,Barang>
     */
    private function getClaimableFoundItems(int $userId): Collection
    {
        if ($userId <= 0) {
            return collect();
        }

        return Barang::query()
            ->with('kategori:id,nama_kategori')
            ->where('status_barang', WorkflowStatus::FOUND_AVAILABLE)
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
                $query->where('status_verifikasi', Admin::STATUS_ACTIVE);
            })
            ->whereDoesntHave('klaims', function ($query): void {
                $query->whereIn('status_verifikasi', [
                    WorkflowStatus::CLAIM_SUBMITTED,
                    WorkflowStatus::CLAIM_UNDER_REVIEW,
                    WorkflowStatus::CLAIM_APPROVED,
                    WorkflowStatus::CLAIM_COMPLETED,
                ]);
            })
            ->select([
                'id',
                'nama_barang',
                'tanggal_ditemukan',
                'lokasi_ditemukan',
                'kategori_id',
                'region_id',
                'status_barang',
            ])
            ->latest('id')
            ->limit(self::DROPDOWN_ITEMS_LIMIT)
            ->get();
    }
}
