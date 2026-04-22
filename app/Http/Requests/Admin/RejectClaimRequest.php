<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectClaimRequest extends FormRequest
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
            'identitas_pelapor_valid' => ['required', 'in:0,1'],
            'detail_barang_valid' => ['required', 'in:0,1'],
            'kronologi_valid' => ['required', 'in:0,1'],
            'bukti_visual_valid' => ['required', 'in:0,1'],
            'kecocokan_data_laporan' => ['required', 'in:0,1'],
            'catatan_verifikasi_admin' => ['nullable', 'string', 'max:2000'],
            'alasan_penolakan' => ['required', 'string', 'max:2000'],
        ];
    }
}
