<?php

namespace App\Services\Super\Profile;

use App\Models\Admin;
use App\Models\SuperAdmin;
use App\Services\Common\ProfileAvatarService;
use App\Support\AdminVerificationStatusPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SuperProfileQueryService
{
    public function __construct(
        private readonly ProfileAvatarService $avatarService
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function buildProfileData(SuperAdmin $superAdmin): array
    {
        $summary = $this->buildSummary((int) $superAdmin->id);

        return [
            'profileAvatar' => $this->avatarService->resolve((string) ($superAdmin->profil ?? '')),
            'totalAdmin' => $summary['total'],
            'pendingAdmin' => $summary['pending'],
            'activeAdmin' => $summary['active'],
            'recentActivities' => $this->buildRecentActivities((int) $superAdmin->id),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildEditData(SuperAdmin $superAdmin): array
    {
        return [
            'profileAvatar' => $this->avatarService->resolve((string) ($superAdmin->profil ?? '')),
        ];
    }

    /**
     * @return array{total:int,pending:int,active:int}
     */
    private function buildSummary(int $superAdminId): array
    {
        $baseQuery = $this->scopedAdminQuery($superAdminId);

        return [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)
                ->where(function ($query) {
                    $query->whereNull('status_verifikasi')->orWhere('status_verifikasi', 'pending');
                })
                ->count(),
            'active' => (clone $baseQuery)->where('status_verifikasi', 'active')->count(),
        ];
    }

    /**
     * @return Collection<int,object>
     */
    private function buildRecentActivities(int $superAdminId): Collection
    {
        return $this->scopedAdminQuery($superAdminId)
            ->latest('updated_at')
            ->limit(8)
            ->get(['id', 'nama', 'instansi', 'status_verifikasi', 'verified_at', 'updated_at'])
            ->map(function (Admin $admin) {
                $statusKey = AdminVerificationStatusPresenter::key((string) ($admin->status_verifikasi ?? 'pending'));

                $statusClass = match ($statusKey) {
                    'active' => 'selesai',
                    'rejected' => 'ditolak',
                    default => 'dalam_peninjauan',
                };

                $statusLabel = AdminVerificationStatusPresenter::label($statusKey);
                $title = match ($statusKey) {
                    'active' => 'Admin ' . $admin->nama . ' disetujui',
                    'rejected' => 'Admin ' . $admin->nama . ' ditolak',
                    default => 'Admin ' . $admin->nama . ' menunggu verifikasi',
                };

                return (object) [
                    'activity_at' => strtotime((string) ($admin->updated_at ?? now())),
                    'title' => $title,
                    'timestamp' => $admin->verified_at ?? $admin->updated_at,
                    'status_class' => $statusClass,
                    'status_label' => $statusLabel,
                    'detail_url' => route('super.admin-verifications.index', ['search' => $admin->nama]),
                    'subtitle' => $admin->instansi ?: 'Instansi belum diisi',
                ];
            })
            ->values();
    }

    private function scopedAdminQuery(int $superAdminId): Builder
    {
        return Admin::query()
            ->where(function (Builder $builder) use ($superAdminId) {
                $builder
                    ->where('super_admin_id', $superAdminId)
                    ->orWhereNull('super_admin_id');
            });
    }
}
