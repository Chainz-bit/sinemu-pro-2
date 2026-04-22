<?php

namespace App\Services\User\Claims;

use App\Actions\Claims\SubmitClaimAction;
use Illuminate\Http\UploadedFile;

class ClaimSubmissionService
{
    public function __construct(
        private readonly SubmitClaimAction $submitClaimAction
    ) {
    }

    /**
     * @param array<string,mixed> $validated
     * @param array<int,UploadedFile> $photos
     * @return array{ok:bool,message:string}
     */
    public function submit(array $validated, array $photos = []): array
    {
        return $this->submitClaimAction->execute($validated, $photos);
    }
}
