<?php

namespace App\Services\Super\Dashboard;

use App\Models\Admin;
use Illuminate\Support\Collection;

class SuperDashboardNewestAdminService
{
    /**
     * @return Collection<int,Admin>
     */
    public function build(int $limit = 5, ?int $superAdminId = null): Collection
    {
        return Admin::query()
            ->when($superAdminId !== null, function ($query) use ($superAdminId) {
                $query->where(function ($builder) use ($superAdminId) {
                    $builder
                        ->where('super_admin_id', $superAdminId)
                        ->orWhereNull('super_admin_id');
                });
            })
            ->latest()
            ->limit($limit)
            ->get();
    }
}
