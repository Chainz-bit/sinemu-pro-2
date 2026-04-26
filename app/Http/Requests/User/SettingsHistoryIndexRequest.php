<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsHistoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['read', 'unread'])],
            'type' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function status(): string
    {
        return (string) ($this->validated('status') ?? '');
    }

    public function typeFilter(): string
    {
        return trim((string) ($this->validated('type') ?? ''));
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
