<?php

namespace App\Http\Requests\Admin;

use App\Support\ManagerPortal;
use Illuminate\Foundation\Http\FormRequest;

class VerifyLostItemReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user(ManagerPortal::guard()) !== null;
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
