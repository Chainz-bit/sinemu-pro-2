<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SubmitLostReportRequest;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Services\User\LostReports\LostReportCommandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LostReportController extends Controller
{
    public function __construct(private readonly LostReportCommandService $commandService)
    {
    }

    public function create(Request $request)
    {
        $userId = (int) Auth::id();
        $editId = (int) $request->query('edit', 0);
        $editingReport = $this->commandService->resolveEditableReport($userId, $editId);

        return view('user.pages.lost-report', [
            'user' => Auth::user(),
            'lostCategoryOptions' => Cache::remember('lost-report:category-options', 600, static fn () => Kategori::query()
                ->forForm()
                ->pluck('nama_kategori')
                ->filter()
                ->values()),
            'editingReport' => $editingReport,
        ]);
    }

    public function store(SubmitLostReportRequest $request): RedirectResponse
    {
        $result = $this->commandService->store($request, $request->validated());
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }

    public function destroy(LaporanBarangHilang $laporanBarangHilang): RedirectResponse
    {
        $result = $this->commandService->destroy($laporanBarangHilang, (int) Auth::id());
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }
}
