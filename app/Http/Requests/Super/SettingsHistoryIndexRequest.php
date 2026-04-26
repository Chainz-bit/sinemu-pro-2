<?php

namespace App\Http\Requests\Super;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsHistoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('super_admin') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pending', 'active', 'rejected'])],
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function status(): string
    {
        return trim((string) ($this->validated('status') ?? ''));
    }

    public function dateFilter(): string
    {
        return trim((string) ($this->validated('date') ?? ''));
    }

    public function search(): string
    {
        return trim((string) ($this->validated('search') ?? ''));
    }
}
