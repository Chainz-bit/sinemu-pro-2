<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardIndexRequest extends FormRequest
{
    public const STATUS_ALL = 'semua';
    public const STATUS_IN_PROGRESS = 'diproses';
    public const STATUS_UNDER_REVIEW = 'dalam_peninjauan';
    public const STATUS_DONE = 'selesai';
    public const STATUS_REJECTED = 'ditolak';

    /**
     * @var array<int, string>
     */
    public const ALLOWED_STATUSES = [
        self::STATUS_ALL,
        self::STATUS_IN_PROGRESS,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_DONE,
        self::STATUS_REJECTED,
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
            'status' => ['nullable', 'string', Rule::in(self::ALLOWED_STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function search(): ?string
    {
        $value = trim((string) $this->query('search', ''));

        return $value === '' ? null : $value;
    }

    public function statusFilter(): string
    {
        $value = trim((string) $this->query('status', self::STATUS_ALL));

        return in_array($value, self::ALLOWED_STATUSES, true) ? $value : self::STATUS_ALL;
    }

    public function pageNumber(): int
    {
        return max((int) $this->query('page', 1), 1);
    }
}
