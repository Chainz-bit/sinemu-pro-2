<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Matching\MatchingCommandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MatchingController extends Controller
{
    public function __construct(private readonly MatchingCommandService $commandService)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'laporan_hilang_id' => ['required', 'integer', 'exists:laporan_barang_hilangs,id'],
            'barang_id' => ['required', 'integer', 'exists:barangs,id'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $result = $this->commandService->confirm((int) Auth::guard('admin')->id(), $validated);
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'laporan_hilang_id' => ['required', 'integer', 'exists:laporan_barang_hilangs,id'],
            'barang_id' => ['required', 'integer', 'exists:barangs,id'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $result = $this->commandService->dismiss((int) Auth::guard('admin')->id(), $validated);
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }
}
