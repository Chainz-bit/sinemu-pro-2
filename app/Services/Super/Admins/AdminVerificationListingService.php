<?php

namespace App\Services\Super\Admins;

use App\Support\AdminVerificationStatusPresenter;
use Illuminate\Database\Eloquent\Builder;

class AdminVerificationListingService
{
    public function __construct(private readonly AdminVerificationScopeQueryService $scopeQueryService)
    {
    }

    public function buildListingQuery(string $search = '', string $status = 'semua', ?int $superAdminId = null): Builder
    {
        $query = $this->scopeQueryService->baseQuery($superAdminId)->latest();

        if ($search !== '') {
            $keyword = trim($search);
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('nama', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%')
                    ->orWhere('username', 'like', '%' . $keyword . '%')
                    ->orWhere('instansi', 'like', '%' . $keyword . '%')
                    ->orWhere('kecamatan', 'like', '%' . $keyword . '%');
            });
        }

        if ($status !== '' && $status !== 'semua') {
            $normalizedStatus = AdminVerificationStatusPresenter::key($status);

            if ($normalizedStatus === 'pending') {
                $query->where(function (Builder $builder) {
                    $builder->whereNull('status_verifikasi')->orWhere('status_verifikasi', 'pending');
                });
            } else {
                $query->where('status_verifikasi', $normalizedStatus);
            }
        }

        return $query;
    }
}
