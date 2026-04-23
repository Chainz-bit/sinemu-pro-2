<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFoundReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori_id' => ['nullable', 'integer', 'exists:kategoris,id'],
            'warna_barang' => ['nullable', 'string', 'max:100'],
            'merek_barang' => ['nullable', 'string', 'max:120'],
            'nomor_seri' => ['nullable', 'string', 'max:150'],
            'deskripsi' => ['required', 'string'],
            'ciri_khusus' => ['nullable', 'string', 'max:2000'],
            'nama_penemu' => ['nullable', 'string', 'max:150'],
            'kontak_penemu' => ['required', 'string', 'max:50'],
            'lokasi_ditemukan' => ['required', 'string', 'max:255'],
            'detail_lokasi_ditemukan' => ['nullable', 'string', 'max:2000'],
            'tanggal_ditemukan' => ['required', 'date'],
            'waktu_ditemukan' => ['nullable', 'date_format:H:i'],
            'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
    }
}
