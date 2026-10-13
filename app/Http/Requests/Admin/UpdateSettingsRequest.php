<?php

namespace App\Http\Requests\Admin;

use App\Support\ManagerPortal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user(ManagerPortal::guard()) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $adminId = $this->user(ManagerPortal::guard())?->id;

        return [
            'kecamatan' => ['required', 'string', 'max:100'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($adminId)],
            'alamat_lengkap' => ['required', 'string', 'max:1200'],
        ];
    }
}
