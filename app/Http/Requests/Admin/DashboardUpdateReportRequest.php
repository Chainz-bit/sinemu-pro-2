<?php

namespace App\Http\Requests\Admin;

use App\Support\WorkflowStatus;
use Illuminate\Validation\Rule;

class DashboardUpdateReportRequest extends DashboardReportTypeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $baseRules = parent::rules();

        return match ($this->reportType()) {
            self::TYPE_LOST => array_merge($baseRules, [
                'nama_barang' => ['required', 'string', 'max:255'],
                'lokasi_hilang' => ['required', 'string', 'max:255'],
                'tanggal_hilang' => ['required', 'date'],
                'keterangan' => ['nullable', 'string', 'max:2000'],
                'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            ]),
            self::TYPE_FOUND => array_merge($baseRules, [
                'nama_barang' => ['required', 'string', 'max:255'],
                'kategori_id' => ['nullable', 'integer', 'exists:kategoris,id'],
                'deskripsi' => ['nullable', 'string', 'max:2000'],
                'lokasi_ditemukan' => ['required', 'string', 'max:255'],
                'tanggal_ditemukan' => ['required', 'date'],
                'lokasi_pengambilan' => ['nullable', 'string', 'max:255'],
                'alamat_pengambilan' => ['nullable', 'string', 'max:255'],
                'penanggung_jawab_pengambilan' => ['nullable', 'string', 'max:255'],
                'kontak_pengambilan' => ['nullable', 'string', 'max:255'],
                'jam_layanan_pengambilan' => ['nullable', 'string', 'max:255'],
                'catatan_pengambilan' => ['nullable', 'string', 'max:2000'],
                'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            ]),
            self::TYPE_CLAIM => array_merge($baseRules, [
                'status_klaim' => ['required', Rule::in(WorkflowStatus::legacyClaimStatuses())],
                'catatan' => ['nullable', 'string', 'max:2000'],
            ]),
            default => $baseRules,
        };
    }
}
