<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SubmitClaimRequest;
use App\Services\User\Claims\ClaimFormPageService;
use App\Services\User\Claims\ClaimSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    public function __construct(
        private readonly ClaimSubmissionService $submissionService,
        private readonly ClaimFormPageService $formPageService
    )
    {
    }

    public function create(Request $request): View
    {
        $requestedBarangId = $request->integer('barang_id');
        $formData = $this->formPageService->build(
            userId: (int) ($request->user()?->id ?? 0),
            requestedBarangId: $requestedBarangId > 0 ? $requestedBarangId : null
        );

        return view('user.pages.claim-create', [
            'foundItems' => $formData['foundItems'],
            'claimableLostReports' => $formData['claimableLostReports'],
            'selectedBarangId' => old('barang_id', $formData['selectedBarangId']),
        ]);
    }

    public function store(SubmitClaimRequest $request): RedirectResponse
    {
        $result = $this->submissionService->submit(
            $request->validated(),
            $request->file('bukti_foto', [])
        );
        if (!$result['ok']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('user.claim-history')->with('status', $result['message']);
    }
}
