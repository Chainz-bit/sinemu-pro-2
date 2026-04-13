<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LostItemIndexRequest extends FormRequest
{
    public const ALLOWED_STATUS = ['pending', 'disetujui', 'ditolak'];

    public const ALLOWED_SORTS = ['terbaru', 'terlama', 'nama_asc', 'nama_desc'];

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
            'status' => ['nullable', 'string', 'in:pending,disetujui,ditolak'],
            'date' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'in:terbaru,terlama,nama_asc,nama_desc'],
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

        return in_array($value, self::ALLOWED_STATUS, true) ? $value : null;
    }

    public function sort(): string
    {
        $value = (string) $this->query('sort', 'terbaru');

        return in_array($value, self::ALLOWED_SORTS, true) ? $value : 'terbaru';
    }

    public function filterDate(): ?string
    {
        $value = (string) $this->query('date', '');

        return $value === '' ? null : $value;
    }

    public function shouldExport(): bool
    {
        return $this->boolean('export');
    }
}
