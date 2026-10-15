<?php

namespace App\Services\Admin\Profile;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Klaim;
use App\Services\Common\ProfileAvatarService;
use App\Support\ClaimStatusPresenter;
use App\Support\IndramayuDistricts;
use App\Support\ManagerPortal;
use App\Support\WorkflowStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AdminProfilePageService
{
    public function __construct(private readonly ProfileAvatarService $avatarService) {}

    /**
     * @return array<string,mixed>
     */
    public function buildProfileData(Admin $admin): array
    {
        $laporanDiajukan = Barang::query()
            ->where('admin_id', $admin->id)
            ->count();

        $klaimMenunggu = Klaim::query()
            ->where('admin_id', $admin->id)
            ->whereIn('status_verifikasi', [WorkflowStatus::CLAIM_SUBMITTED, WorkflowStatus::CLAIM_UNDER_REVIEW])
            ->count();

        $selesaiDitangani = Klaim::query()
            ->where('admin_id', $admin->id)
            ->whereIn('status_verifikasi', [
                WorkflowStatus::CLAIM_APPROVED,
                WorkflowStatus::CLAIM_REJECTED,
                WorkflowStatus::CLAIM_COMPLETED,
            ])
            ->count();

        [$verificationLabel, $verificationClass] = $this->resolveVerificationStatus($admin);

        return [
            'laporanDiajukan' => $laporanDiajukan,
            'klaimMenunggu' => $klaimMenunggu,
            'selesaiDitangani' => $selesaiDitangani,
            'recentActivities' => $this->buildRecentActivities((int) $admin->id),
            'profileAvatar' => $this->avatarService->resolve((string) ($admin->profil ?? '')),
            'verificationLabel' => $verificationLabel,
            'verificationClass' => $verificationClass,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildEditData(Admin $admin): array
    {
        [$verificationLabel, $verificationClass] = $this->resolveVerificationStatus($admin);

        return [
            'profileAvatar' => $this->avatarService->resolve((string) ($admin->profil ?? '')),
            'verificationLabel' => $verificationLabel,
            'verificationClass' => $verificationClass,
            'kecamatanOptions' => $this->getKecamatanOptions(),
        ];
    }

    public function update(Admin $admin, array $validated, ?UploadedFile $photo): void
    {
        $oldProfilePath = null;
        $newProfilePath = null;
        if ($photo) {
            $oldProfilePath = $this->deletablePublicPath((string) ($admin->profil ?? ''));
            $newProfilePath = $photo->store('profil-admin/'.now()->format('Y/m'), 'public');
            $validated['profil'] = $newProfilePath;
        }

        try {
            $admin->forceFill($validated)->save();
        } catch (Throwable $exception) {
            if ($newProfilePath) {
                Storage::disk('public')->delete($newProfilePath);
            }

            throw $exception;
        }

        if ($oldProfilePath) {
            Storage::disk('public')->delete($oldProfilePath);
        }
    }

    private function deletablePublicPath(string $path): ?string
    {
        $path = trim($path);

        return $path !== ''
            && ! str_starts_with($path, 'http://')
            && ! str_starts_with($path, 'https://')
            && ! str_starts_with($path, '/')
            ? $path
            : null;
    }

    private function buildRecentActivities(int $adminId): Collection
    {
        $inputActivities = Barang::query()
            ->where('admin_id', $adminId)
            ->latest('updated_at')
            ->limit(8)
            ->get([
                'id',
                'nama_barang',
                'status_barang',
                'created_at',
                'updated_at',
            ])
            ->map(function (Barang $barang) {
                $statusClass = match ($barang->status_barang) {
                    WorkflowStatus::FOUND_CLAIMED, WorkflowStatus::FOUND_RETURNED => 'selesai',
                    WorkflowStatus::FOUND_CLAIM_IN_PROGRESS => 'dalam_peninjauan',
                    default => 'diproses',
                };

                $statusLabel = match ($barang->status_barang) {
                    WorkflowStatus::FOUND_CLAIMED, WorkflowStatus::FOUND_RETURNED => 'SELESAI',
                    WorkflowStatus::FOUND_CLAIM_IN_PROGRESS => 'SEDANG DIKLAIM',
                    default => 'DIPROSES',
                };

                return (object) [
                    'activity_at' => strtotime((string) ($barang->updated_at ?? $barang->created_at)),
                    'title' => 'Anda memasukkan data barang temuan '.$barang->nama_barang,
                    'timestamp' => $barang->updated_at ?? $barang->created_at,
                    'status_class' => $statusClass,
                    'status_label' => $statusLabel,
                    'detail_url' => route(ManagerPortal::routeName('found-items.show'), $barang->id),
                ];
            });

        $claimActivities = Klaim::query()
            ->where('admin_id', $adminId)
            ->with([
                'barang:id,nama_barang',
                'laporanHilang:id,nama_barang',
            ])
            ->latest('updated_at')
            ->limit(8)
            ->get([
                'id',
                'barang_id',
                'laporan_hilang_id',
                'status_klaim',
                'status_verifikasi',
                'created_at',
                'updated_at',
            ])
            ->map(function (Klaim $klaim) {
                $namaBarang = $klaim->barang?->nama_barang
                    ?? $klaim->laporanHilang?->nama_barang
                    ?? 'barang';

                $claimKey = ClaimStatusPresenter::key(
                    statusKlaim: (string) $klaim->status_klaim,
                    statusVerifikasi: (string) ($klaim->status_verifikasi ?? ''),
                    statusBarang: null
                );
                $statusClass = match ($claimKey) {
                    'selesai' => 'selesai',
                    'ditolak' => 'ditolak',
                    'disetujui' => 'diproses',
                    default => 'dalam_peninjauan',
                };
                $statusLabel = ClaimStatusPresenter::label($claimKey);
                $kataKerja = match ($claimKey) {
                    'disetujui' => 'disetujui',
                    'ditolak' => 'ditolak',
                    'selesai' => 'diselesaikan',
                    default => 'menunggu verifikasi',
                };

                return (object) [
                    'activity_at' => strtotime((string) ($klaim->updated_at ?? $klaim->created_at)),
                    'title' => 'Klaim barang '.$namaBarang.' '.$kataKerja,
                    'timestamp' => $klaim->updated_at ?? $klaim->created_at,
                    'status_class' => $statusClass,
                    'status_label' => $statusLabel,
                    'detail_url' => route(ManagerPortal::routeName('claim-verifications.show'), $klaim->id),
                ];
            });

        return $inputActivities
            ->toBase()
            ->merge($claimActivities->toBase())
            ->sortByDesc('activity_at')
            ->take(8)
            ->values();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveVerificationStatus(Admin $admin): array
    {
        return match ((string) ($admin->status_verifikasi ?? 'active')) {
            'active' => ['Terverifikasi', 'is-active'],
            'pending' => ['Menunggu Verifikasi', 'is-pending'],
            'rejected' => ['Verifikasi Ditolak', 'is-rejected'],
            default => ['Status Tidak Diketahui', 'is-unknown'],
        };
    }

    /**
     * @return array<int, string>
     */
    private function getKecamatanOptions(): array
    {
        return IndramayuDistricts::names();
    }
}
