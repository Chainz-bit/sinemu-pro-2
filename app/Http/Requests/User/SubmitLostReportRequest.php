<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class SubmitLostReportRequest extends FormRequest
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
            'report_id' => ['nullable', 'integer', 'exists:laporan_barang_hilangs,id'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori_barang' => ['nullable', 'string', 'max:100'],
            'warna_barang' => ['nullable', 'string', 'max:100'],
            'merek_barang' => ['nullable', 'string', 'max:120'],
            'nomor_seri' => ['nullable', 'string', 'max:150'],
            'lokasi_hilang' => ['required', 'string', 'max:255'],
            'detail_lokasi_hilang' => ['nullable', 'string', 'max:2000'],
            'tanggal_hilang' => ['required', 'date'],
            'waktu_hilang' => ['nullable', 'date_format:H:i'],
            'keterangan' => ['required', 'string'],
            'ciri_khusus' => ['nullable', 'string', 'max:2000'],
            'kontak_pelapor' => ['required', 'string', 'max:50'],
            'bukti_kepemilikan' => ['nullable', 'string', 'max:2000'],
            'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
    }
}
