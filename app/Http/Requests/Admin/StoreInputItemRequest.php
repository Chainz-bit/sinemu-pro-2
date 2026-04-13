<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreInputItemRequest extends FormRequest
{
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
            'jenis_laporan' => ['required', 'in:hilang,temuan'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori_id' => ['nullable', 'integer', 'exists:kategoris,id'],
            'tanggal_waktu' => ['required', 'date'],
            'lokasi' => ['required', 'string', 'max:255'],
            'nama_pelapor' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'lokasi_pengambilan' => ['required_if:jenis_laporan,temuan', 'nullable', 'string', 'max:255'],
            'alamat_pengambilan' => ['required_if:jenis_laporan,temuan', 'nullable', 'string', 'max:255'],
            'penanggung_jawab_pengambilan' => ['required_if:jenis_laporan,temuan', 'nullable', 'string', 'max:255'],
            'kontak_pengambilan' => ['required_if:jenis_laporan,temuan', 'nullable', 'string', 'max:255'],
            'jam_layanan_pengambilan' => ['nullable', 'string', 'max:255'],
            'catatan_pengambilan' => ['nullable', 'string'],
        ];
    }
}
