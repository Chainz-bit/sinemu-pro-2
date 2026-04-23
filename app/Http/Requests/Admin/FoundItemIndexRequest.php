<?php

namespace App\Http\Requests\Admin;

use App\Support\WorkflowStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FoundItemIndexRequest extends FormRequest
{
    public const SORT_NEWEST = 'terbaru';
    public const SORT_OLDEST = 'terlama';
    public const SORT_NAME_ASC = 'nama_asc';
    public const SORT_NAME_DESC = 'nama_desc';

    public const ALLOWED_SORTS = [
        self::SORT_NEWEST,
        self::SORT_OLDEST,
        self::SORT_NAME_ASC,
        self::SORT_NAME_DESC,
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(WorkflowStatus::foundItemStatuses())],
            'date' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', Rule::in(self::ALLOWED_SORTS)],
            'export' => ['nullable', 'boolean'],
        ];
    }

    public function search(): ?string
    {
        $value = trim((string) $this->query('search', ''));

        return $value === '' ? null : $value;
    }

    public function status(): ?string
    {
        $value = (string) $this->query('status', '');

        return in_array($value, WorkflowStatus::foundItemStatuses(), true) ? $value : null;
    }

    public function filterDate(): ?string
    {
        $value = (string) $this->query('date', '');

        return $value === '' ? null : $value;
    }

    public function sort(): string
    {
        $value = (string) $this->query('sort', self::SORT_NEWEST);

        return in_array($value, self::ALLOWED_SORTS, true) ? $value : self::SORT_NEWEST;
    }

    public function shouldExport(): bool
    {
        return $this->boolean('export');
    }
}
