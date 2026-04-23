<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VerifyLostItemReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status_laporan' => ['required', 'in:approved,rejected'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
