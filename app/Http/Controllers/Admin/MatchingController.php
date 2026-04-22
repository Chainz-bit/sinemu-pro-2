<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmMatchRequest;
use App\Http\Requests\Admin\DismissMatchRequest;
use App\Services\Admin\Matching\MatchingCommandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class MatchingController extends Controller
{
    public function __construct(private readonly MatchingCommandService $commandService)
    {
    }

    public function store(ConfirmMatchRequest $request): RedirectResponse
    {
        $result = $this->commandService->confirm((int) Auth::guard('admin')->id(), $request->validated());
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }

    public function dismiss(DismissMatchRequest $request): RedirectResponse
    {
        $result = $this->commandService->dismiss((int) Auth::guard('admin')->id(), $request->validated());
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }
}
