<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmMatchRequest;
use App\Http\Requests\Admin\DismissMatchRequest;
use App\Services\Admin\Matching\MatchingCommandService;
use App\Support\ManagerPortal;
use Illuminate\Http\RedirectResponse;

class MatchingController extends Controller
{
    public function __construct(private readonly MatchingCommandService $commandService) {}

    public function store(ConfirmMatchRequest $request): RedirectResponse
    {
        $result = $this->commandService->confirm((int) ManagerPortal::id(), $request->validated());
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }

    public function dismiss(DismissMatchRequest $request): RedirectResponse
    {
        $result = $this->commandService->dismiss((int) ManagerPortal::id(), $request->validated());
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }
}
