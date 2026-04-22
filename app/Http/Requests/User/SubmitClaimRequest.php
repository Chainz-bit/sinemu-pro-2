<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class SubmitClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'barang_id' => ['required', 'integer', 'exists:barangs,id'],
            'laporan_hilang_id' => ['required', 'integer', 'exists:laporan_barang_hilangs,id'],
            'kontak_pelapor' => ['required', 'string', 'max:50'],
            'bukti_kepemilikan' => ['required', 'string', 'max:2000'],
            'bukti_ciri_khusus' => ['required', 'string', 'max:2000'],
            'bukti_detail_isi' => ['nullable', 'string', 'max:2000'],
            'bukti_lokasi_spesifik' => ['required', 'string', 'max:255'],
            'bukti_waktu_hilang' => ['required', 'regex:/^([01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'bukti_foto' => ['required', 'array', 'min:1', 'max:3'],
            'bukti_foto.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'catatan' => ['nullable', 'string'],
            'persetujuan_klaim' => ['accepted'],
        ];
    }
}
