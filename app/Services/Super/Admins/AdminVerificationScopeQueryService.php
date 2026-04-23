<?php

namespace App\Services\Super\Admins;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;

class AdminVerificationScopeQueryService
{
    public function baseQuery(?int $superAdminId = null): Builder
    {
        $query = Admin::query();

        if ($superAdminId !== null) {
            $query->where(function (Builder $builder) use ($superAdminId) {
                $builder
                    ->where('super_admin_id', $superAdminId)
                    ->orWhereNull('super_admin_id');
            });
        }

        return $query;
    }
}
