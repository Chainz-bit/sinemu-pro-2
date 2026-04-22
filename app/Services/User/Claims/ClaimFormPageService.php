<?php

namespace App\Services\User\Claims;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
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
        $foundItems = $this->getMatchedFoundItems($claimableLostReports);

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
     * @param Collection<int,LaporanBarangHilang> $reports
     * @return Collection<int,Barang>
     */
    private function getMatchedFoundItems(Collection $reports): Collection
    {
        if ($reports->isEmpty()) {
            return collect();
        }

        $reportIds = $reports->pluck('id')->map(fn ($id) => (int) $id)->all();
        $matchedBarangIds = Pencocokan::query()
            ->whereIn('laporan_hilang_id', $reportIds)
            ->whereIn('status_pencocokan', [
                WorkflowStatus::MATCH_CONFIRMED,
                WorkflowStatus::MATCH_CLAIM_IN_PROGRESS,
                WorkflowStatus::MATCH_CLAIM_REJECTED,
            ])
            ->pluck('barang_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($matchedBarangIds === []) {
            return collect();
        }

        $query = Barang::query()
            ->with('kategori:id,nama_kategori')
            ->whereIn('id', $matchedBarangIds)
            ->where('status_barang', 'tersedia')
            ->whereIn('status_laporan', [
                WorkflowStatus::REPORT_APPROVED,
                WorkflowStatus::REPORT_MATCHED,
                WorkflowStatus::REPORT_CLAIMED,
            ])
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
