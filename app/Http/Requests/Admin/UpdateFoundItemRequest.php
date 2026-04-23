<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFoundItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
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
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'ciri_khusus' => ['nullable', 'string', 'max:2000'],
            'nama_penemu' => ['nullable', 'string', 'max:150'],
            'kontak_penemu' => ['nullable', 'string', 'max:50'],
            'lokasi_ditemukan' => ['required', 'string', 'max:255'],
            'detail_lokasi_ditemukan' => ['nullable', 'string', 'max:2000'],
            'tanggal_ditemukan' => ['required', 'date'],
            'waktu_ditemukan' => ['nullable', 'date_format:H:i'],
            'lokasi_pengambilan' => ['nullable', 'string', 'max:255'],
            'alamat_pengambilan' => ['nullable', 'string', 'max:255'],
            'penanggung_jawab_pengambilan' => ['nullable', 'string', 'max:255'],
            'kontak_pengambilan' => ['nullable', 'string', 'max:255'],
            'jam_layanan_pengambilan' => ['nullable', 'string', 'max:255'],
            'catatan_pengambilan' => ['nullable', 'string', 'max:2000'],
            'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
    }
}
