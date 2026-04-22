<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'laporan_hilang_id' => ['required', 'integer', 'exists:laporan_barang_hilangs,id'],
            'barang_id' => ['required', 'integer', 'exists:barangs,id'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
