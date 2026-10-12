<?php

namespace App\Services\Admin\Matching;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Services\UserNotificationService;
use App\States\Matching\MatchStateResolver;
use App\Support\ManagerPortal;
use App\Support\RoleLabels;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\DB;

class MatchingCommandService
{
    public function __construct(
        private readonly MatchStateResolver $matchStateResolver
    ) {}

    /**
     * @param  array<string,mixed>  $validated
     * @return array{ok:bool,message:string}
     */
    public function confirm(int $adminId, array $validated): array
    {
        $laporanId = (int) $validated['laporan_hilang_id'];
        $barangId = (int) $validated['barang_id'];

        $result = DB::transaction(function () use ($laporanId, $barangId, $validated, $adminId): array {
            $laporan = LaporanBarangHilang::query()->whereKey($laporanId)->lockForUpdate()->firstOrFail();
            $barang = Barang::query()->whereKey($barangId)->lockForUpdate()->firstOrFail();

            $this->ensureAdminCanAccessPair($laporan, $barang);

            $existingPair = Pencocokan::query()
                ->where('laporan_hilang_id', $laporanId)
                ->where('barang_id', $barangId)
                ->lockForUpdate()
                ->first();

            if ((string) ($existingPair?->status_pencocokan ?? '') === WorkflowStatus::MATCH_CONFIRMED) {
                return ['ok' => true, 'already_confirmed' => true, 'message' => 'Pencocokan sudah dikonfirmasi.'];
            }

            if ((string) $laporan->status_laporan !== WorkflowStatus::REPORT_APPROVED) {
                return ['ok' => false, 'message' => 'Laporan barang hilang harus disetujui sebelum dicocokkan.'];
            }
            if ((string) $barang->status_laporan !== WorkflowStatus::REPORT_APPROVED) {
                return ['ok' => false, 'message' => 'Laporan barang temuan harus disetujui sebelum dicocokkan.'];
            }

            $blockingStatuses = WorkflowStatus::blockingMatchStatuses();

            $isBlockedByOtherFoundItem = Pencocokan::query()
                ->where('laporan_hilang_id', $laporanId)
                ->where('barang_id', '!=', $barangId)
                ->whereIn('status_pencocokan', $blockingStatuses)
                ->lockForUpdate()
                ->exists();
            if ($isBlockedByOtherFoundItem) {
                return ['ok' => false, 'message' => 'Laporan barang hilang ini masih punya pencocokan aktif lain.'];
            }

            $isBlockedByOtherLostReport = Pencocokan::query()
                ->where('barang_id', $barangId)
                ->where('laporan_hilang_id', '!=', $laporanId)
                ->whereIn('status_pencocokan', $blockingStatuses)
                ->lockForUpdate()
                ->exists();
            if ($isBlockedByOtherLostReport) {
                return ['ok' => false, 'message' => 'Barang temuan ini masih terikat pada pencocokan aktif lain.'];
            }

            if (! $this->matchStateResolver->resolve($existingPair)->canConfirm()) {
                return ['ok' => false, 'message' => 'Pasangan ini tidak berada pada state yang dapat dikonfirmasi.'];
            }

            $pencocokan = Pencocokan::query()->updateOrCreate(
                [
                    'laporan_hilang_id' => $laporanId,
                    'barang_id' => $barangId,
                ],
                [
                    'admin_id' => $adminId,
                    'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
                    'catatan' => $validated['catatan'] ?? null,
                    'matched_at' => now(),
                ]
            );

            $laporan->update(['status_laporan' => WorkflowStatus::REPORT_MATCHED]);
            $barang->update(['status_laporan' => WorkflowStatus::REPORT_MATCHED]);

            return [
                'ok' => true,
                'laporan' => $laporan,
                'barang' => $barang,
                'pencocokan' => $pencocokan,
            ];
        });

        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Pencocokan gagal diproses.')];
        }

        if (($result['already_confirmed'] ?? false) === true) {
            return ['ok' => true, 'message' => (string) $result['message']];
        }

        /** @var LaporanBarangHilang $laporan */
        $laporan = $result['laporan'];
        /** @var Barang $barang */
        $barang = $result['barang'];
        /** @var Pencocokan $pencocokan */
        $pencocokan = $result['pencocokan'];

        if (! is_null($laporan->user_id)) {
            UserNotificationService::notifyUser(
                userId: (int) $laporan->user_id,
                type: 'pencocokan_ditemukan',
                title: 'Ada Kecocokan Barang',
                message: ucfirst(RoleLabels::managerLower()).' menemukan barang temuan yang diduga cocok dengan laporan Anda: '.$laporan->nama_barang.'. Lanjutkan dengan proses klaim.',
                actionUrl: route('home.found-detail', $barang->id),
                meta: [
                    'pencocokan_id' => $pencocokan->id,
                    'laporan_hilang_id' => $laporan->id,
                    'barang_id' => $barang->id,
                ]
            );
        }

        if (! is_null($barang->user_id) && (int) $barang->user_id !== (int) $laporan->user_id) {
            UserNotificationService::notifyUser(
                userId: (int) $barang->user_id,
                type: 'pencocokan_ditemukan',
                title: 'Barang Temuan Diduga Cocok',
                message: ucfirst(RoleLabels::managerLower()).' menandai barang temuan Anda sebagai diduga cocok dengan laporan barang hilang: '.$barang->nama_barang.'.',
                actionUrl: route('home.lost-detail', $laporan->id),
                meta: [
                    'pencocokan_id' => $pencocokan->id,
                    'laporan_hilang_id' => $laporan->id,
                    'barang_id' => $barang->id,
                ]
            );
        }

        return ['ok' => true, 'message' => 'Pencocokan berhasil ditandai dan notifikasi telah dikirim.'];
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array{ok:bool,message:string}
     */
    public function dismiss(int $adminId, array $validated): array
    {
        $laporanId = (int) $validated['laporan_hilang_id'];
        $barangId = (int) $validated['barang_id'];

        $result = DB::transaction(function () use ($laporanId, $barangId, $validated, $adminId): array {
            $laporan = LaporanBarangHilang::query()->whereKey($laporanId)->lockForUpdate()->firstOrFail();
            $barang = Barang::query()->whereKey($barangId)->lockForUpdate()->firstOrFail();

            $this->ensureAdminCanAccessPair($laporan, $barang);

            if ((string) $laporan->status_laporan !== WorkflowStatus::REPORT_APPROVED) {
                return ['ok' => false, 'message' => 'Laporan barang hilang harus disetujui sebelum proses pencocokan.'];
            }
            if ((string) $barang->status_laporan !== WorkflowStatus::REPORT_APPROVED) {
                return ['ok' => false, 'message' => 'Laporan barang temuan harus disetujui sebelum proses pencocokan.'];
            }

            $blockingPairExists = Pencocokan::query()
                ->where('laporan_hilang_id', $laporanId)
                ->where('barang_id', $barangId)
                ->whereIn('status_pencocokan', WorkflowStatus::blockingMatchStatuses())
                ->lockForUpdate()
                ->exists();

            if ($blockingPairExists) {
                return ['ok' => false, 'message' => 'Pasangan ini sudah berada pada pencocokan aktif.'];
            }

            $existingPair = Pencocokan::query()
                ->where('laporan_hilang_id', $laporanId)
                ->where('barang_id', $barangId)
                ->lockForUpdate()
                ->first();
            if (! $this->matchStateResolver->resolve($existingPair)->canDismiss()) {
                return ['ok' => false, 'message' => 'Pasangan ini tidak berada pada state yang dapat ditandai tidak cocok.'];
            }

            Pencocokan::query()->updateOrCreate(
                [
                    'laporan_hilang_id' => $laporanId,
                    'barang_id' => $barangId,
                ],
                [
                    'admin_id' => $adminId,
                    'status_pencocokan' => WorkflowStatus::MATCH_CANCELLED,
                    'catatan' => $validated['catatan'] ?? 'Ditandai tidak cocok oleh '.RoleLabels::managerLower().'.',
                    'matched_at' => now(),
                ]
            );

            return ['ok' => true];
        });

        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Penandaan kandidat tidak cocok gagal diproses.')];
        }

        return ['ok' => true, 'message' => 'Kandidat ditandai tidak cocok dan tidak akan ditampilkan lagi.'];
    }

    private function ensureAdminCanAccessPair(LaporanBarangHilang $laporan, Barang $barang): void
    {
        $admin = ManagerPortal::user();
        abort_if(! $admin || empty($admin->region_id), 403, ucfirst(RoleLabels::managerLower()).' belum memiliki wilayah akses.');

        $adminRegionId = (int) $admin->region_id;
        $laporanRegionId = (int) ($laporan->region_id ?? 0);
        $barangRegionId = (int) ($barang->region_id ?? 0);

        abort_if($laporanRegionId <= 0, 403, 'Laporan belum memiliki wilayah yang dapat diproses.');
        abort_if($barangRegionId <= 0, 403, 'Barang belum memiliki wilayah yang dapat diproses.');
        abort_if($laporanRegionId !== $barangRegionId, 403, 'Laporan dan barang temuan harus berasal dari wilayah yang sama.');
        abort_if($laporanRegionId !== $adminRegionId, 403, 'Anda tidak memiliki akses ke laporan dari wilayah lain.');
        abort_if($barangRegionId !== $adminRegionId, 403, 'Anda tidak memiliki akses ke barang dari wilayah lain.');
    }
}
