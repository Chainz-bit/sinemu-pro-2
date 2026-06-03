<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\User\SubmitClaimRequest;

class StoreKlaimRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        $rules = (new SubmitClaimRequest())->rules();

        unset($rules['barang_id']);

        return $rules;
    }
}
