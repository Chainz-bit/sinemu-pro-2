<?php

namespace App\Http\Requests\Admin;

use App\Support\WorkflowStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFoundItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status_barang' => ['required', Rule::in(WorkflowStatus::foundItemStatuses())],
            'catatan_status' => ['nullable', 'string', 'max:500'],
        ];
    }
}
