<?php

namespace App\Http\Requests\Super;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('super_admin') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $superAdminId = $this->user('super_admin')?->id;

        return [
            'nama' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('super_admins', 'username')->ignore($superAdminId)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('super_admins', 'email')->ignore($superAdminId)],
        ];
    }
}
