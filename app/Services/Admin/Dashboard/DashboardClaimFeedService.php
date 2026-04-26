<?php

namespace App\Services\Admin\Dashboard;

use App\Models\Klaim;
use App\Support\ClaimStatusPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardClaimFeedService
{
    public function build(bool $foundHasHomeFlag, bool $lostHasHomeFlag): Collection
    {
        return Klaim::query()
            ->with([
                'barang' => function ($query) use ($foundHasHomeFlag) {
                    $columns = ['id', 'nama_barang', 'lokasi_ditemukan', 'foto_barang'];
                    if ($foundHasHomeFlag) {
                        $columns[] = 'tampil_di_home';
                    }
                    $query->select($columns);
                },
                'laporanHilang' => function ($query) use ($lostHasHomeFlag) {
                    $columns = ['id', 'nama_barang', 'lokasi_hilang', 'foto_barang'];
                    if ($lostHasHomeFlag) {
                        $columns[] = 'tampil_di_home';
                    }
                    $query->select($columns);
                },
                'user:id,nama,name',
            ])
            ->select(array_values(array_filter([
                'id',
                'status_klaim',
                Schema::hasColumn('klaims', 'status_verifikasi') ? 'status_verifikasi' : null,
                'catatan',
                'created_at',
                'updated_at',
                'barang_id',
                'laporan_hilang_id',
            ])))
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($claim) => $this->presentClaim($claim));
    }

    private function presentClaim(Klaim $claim): object
    {
        $claimStatusKey = ClaimStatusPresenter::key(
            statusKlaim: (string) $claim->status_klaim,
            statusVerifikasi: (string) ($claim->status_verifikasi ?? ''),
            statusBarang: (string) ($claim->barang?->status_barang ?? '')
        );
        $statusPayload = $this->buildStatusPayload($claimStatusKey);
        $namaBarang = $claim->barang?->nama_barang
            ?? $claim->laporanHilang?->nama_barang
            ?? 'Klaim Barang';
        $lokasi = $claim->barang?->lokasi_ditemukan
            ?? $claim->laporanHilang?->lokasi_hilang
            ?? 'Lokasi tidak tersedia';
        $pelapor = $claim->user?->nama ?? $claim->user?->name ?? 'Pengguna';
        $activityAt = strtotime((string) ($claim->updated_at ?? $claim->created_at));

        return (object) [
            'id' => (int) $claim->id,
            'type' => 'klaim',
            'item_name' => $namaBarang,
            'item_detail' => 'Pelapor: ' . $pelapor . ' - Layanan: Verifikasi Klaim - ' . $lokasi,
            'incident_date' => $claim->created_at,
            'created_at' => $claim->created_at,
            'activity_at' => $activityAt,
            'status' => $statusPayload['status'],
            'status_class' => $statusPayload['status_class'],
            'status_text' => $statusPayload['status_text'],
            'status_label' => 'Verifikasi Klaim',
            'avatar' => 'K',
            'avatar_class' => 'avatar-claim',
            'foto_barang' => $claim->barang?->foto_barang ?? $claim->laporanHilang?->foto_barang,
            'detail_url' => $this->resolveDetailUrl($claim),
            'edit_url' => route('admin.claim-verifications.show', $claim->id),
            'edit_status_klaim' => $claim->status_klaim,
            'edit_catatan' => $claim->catatan,
            'update_url' => route('admin.dashboard.reports.update', ['type' => 'klaim', 'id' => $claim->id]),
            'upload_home_url' => $this->resolveUploadHomeUrl($claim),
            'home_published' => $this->resolveHomePublished($claim),
            'target_url' => $this->resolveTargetUrl($namaBarang, $claimStatusKey),
            'target_label' => 'Buka Verifikasi Klaim',
            'delete_url' => route('admin.claim-verifications.destroy', $claim->id),
        ];
    }

    /**
     * @return array{status:string,status_class:string,status_text:string}
     */
    private function buildStatusPayload(string $claimStatusKey): array
    {
        return [
            'status' => match ($claimStatusKey) {
                'disetujui' => 'diproses',
                'selesai' => 'selesai',
                'ditolak' => 'ditolak',
                default => 'dalam_peninjauan',
            },
            'status_class' => ClaimStatusPresenter::cssClass($claimStatusKey),
            'status_text' => ClaimStatusPresenter::label($claimStatusKey),
        ];
    }

    private function resolveDetailUrl(Klaim $claim): string
    {
        return match (true) {
            !is_null($claim->barang_id) => route('admin.found-items.show', $claim->barang_id),
            !is_null($claim->laporan_hilang_id) => route('admin.lost-items.show', $claim->laporan_hilang_id),
            default => route('admin.claim-verifications.show', $claim->id),
        };
    }

    private function resolveUploadHomeUrl(Klaim $claim): ?string
    {
        return match (true) {
            !is_null($claim->barang_id) => route('admin.dashboard.reports.publish-home', ['type' => 'temuan', 'id' => $claim->barang_id]),
            !is_null($claim->laporan_hilang_id) => route('admin.dashboard.reports.publish-home', ['type' => 'hilang', 'id' => $claim->laporan_hilang_id]),
            default => null,
        };
    }

    private function resolveHomePublished(Klaim $claim): bool
    {
        return match (true) {
            !is_null($claim->barang?->tampil_di_home ?? null) => (bool) $claim->barang?->tampil_di_home,
            !is_null($claim->laporanHilang?->tampil_di_home ?? null) => (bool) $claim->laporanHilang?->tampil_di_home,
            default => false,
        };
    }

    private function resolveTargetUrl(string $namaBarang, string $claimStatusKey): string
    {
        return route('admin.claim-verifications', [
            'search' => $namaBarang,
            'status' => match ($claimStatusKey) {
                'menunggu' => 'menunggu',
                'selesai' => 'selesai',
                default => $claimStatusKey,
            },
        ]);
    }
}
