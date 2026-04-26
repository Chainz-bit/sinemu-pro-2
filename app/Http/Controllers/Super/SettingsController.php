<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Http\Requests\Super\SettingsHistoryIndexRequest;
use App\Http\Requests\Super\UpdateSettingsRequest;
use App\Models\Admin;
use App\Models\SuperAdmin;
use App\Support\AdminVerificationStatusPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $superAdmin = $this->currentSuperAdmin();

        return view('super.pages.settings', compact('superAdmin'));
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $superAdmin = $this->currentSuperAdmin();

        $superAdmin->forceFill($request->validated())->save();

        return redirect()
            ->route('super.settings')
            ->with('status', 'Pengaturan super admin berhasil diperbarui.');
    }

    public function history(SettingsHistoryIndexRequest $request): View
    {
        $superAdmin = $this->currentSuperAdmin();

        $query = Admin::query()
            ->where(function (Builder $builder) use ($superAdmin) {
                $builder
                    ->where('super_admin_id', $superAdmin->id)
                    ->orWhereNull('super_admin_id');
            });

        $statusFilter = $request->status();
        if ($statusFilter !== '') {
            if ($statusFilter === 'pending') {
                $query->where(function (Builder $builder) {
                    $builder->whereNull('status_verifikasi')->orWhere('status_verifikasi', 'pending');
                });
            } else {
                $query->where('status_verifikasi', $statusFilter);
            }
        }

        $dateFilter = $request->dateFilter();
        if ($dateFilter !== '') {
            $query->whereDate('updated_at', $dateFilter);
        }

        $search = $request->search();
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('nama', 'like', '%' . $search . '%')
                    ->orWhere('instansi', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $histories = $query
            ->orderByDesc('verified_at')
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString();

        $baseSummaryQuery = Admin::query()
            ->where(function (Builder $builder) use ($superAdmin) {
                $builder
                    ->where('super_admin_id', $superAdmin->id)
                    ->orWhereNull('super_admin_id');
            });

        $summary = [
            'total' => (clone $baseSummaryQuery)->count(),
            'pending' => (clone $baseSummaryQuery)
                ->where(function (Builder $builder) {
                    $builder->whereNull('status_verifikasi')->orWhere('status_verifikasi', 'pending');
                })->count(),
            'reviewed' => (clone $baseSummaryQuery)->whereIn('status_verifikasi', ['active', 'rejected'])->count(),
        ];

        return view('super.pages.settings-history', [
            'superAdmin' => $superAdmin,
            'histories' => $histories,
            'summary' => $summary,
            'statusFilter' => $statusFilter,
            'dateFilter' => $dateFilter,
            'search' => $search,
            'statusPresenter' => AdminVerificationStatusPresenter::class,
        ]);
    }

    private function currentSuperAdmin(): SuperAdmin
    {
        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = Auth::guard('super_admin')->user();
        abort_if(!$superAdmin, 403);

        return $superAdmin;
    }
}
