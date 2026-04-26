<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsLogIndexRequest;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Admin;
use App\Models\AdminNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        abort_if(!$admin, 403);

        return view('admin.pages.settings', compact('admin'));
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        abort_if(!$admin, 403);

        $admin->forceFill($request->validated())->save();

        return redirect()
            ->route('admin.settings')
            ->with('status', 'Pengaturan sistem berhasil diperbarui.');
    }

    public function logs(SettingsLogIndexRequest $request): View
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        abort_if(!$admin, 403);

        $query = AdminNotification::query()
            ->where('admin_id', $admin->id)
            ->latest('created_at');

        $statusFilter = $request->status();
        if ($statusFilter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($statusFilter === 'read') {
            $query->whereNotNull('read_at');
        }

        $typeFilter = $request->typeFilter();
        if ($typeFilter !== '') {
            $query->where('type', $typeFilter);
        }

        $dateFilter = $request->dateFilter();
        if ($dateFilter !== '') {
            $query->whereDate('created_at', $dateFilter);
        }

        $search = $request->search();
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('message', 'like', '%' . $search . '%');
            });
        }

        $logs = $query->paginate(12)->withQueryString();

        $typeOptions = [
            'barang_temuan_baru' => 'Barang Temuan Baru',
            'laporan_hilang_baru' => 'Laporan Hilang Baru',
            'klaim_baru' => 'Klaim Baru',
        ];

        // Tetap tampilkan tipe tambahan jika ada data lama/tipe custom di database.
        $extraTypes = AdminNotification::query()
            ->where('admin_id', $admin->id)
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->filter(function ($type) use ($typeOptions) {
                return !array_key_exists((string) $type, $typeOptions);
            });

        foreach ($extraTypes as $extraType) {
            $typeOptions[(string) $extraType] = str_replace('_', ' ', ucwords((string) $extraType, '_'));
        }

        $summary = [
            'total' => AdminNotification::query()->where('admin_id', $admin->id)->count(),
            'unread' => AdminNotification::query()->where('admin_id', $admin->id)->whereNull('read_at')->count(),
            'read' => AdminNotification::query()->where('admin_id', $admin->id)->whereNotNull('read_at')->count(),
        ];

        return view('admin.pages.settings-logs', compact(
            'logs',
            'summary',
            'typeOptions',
            'statusFilter',
            'typeFilter',
            'dateFilter',
            'search'
        ));
    }
}
