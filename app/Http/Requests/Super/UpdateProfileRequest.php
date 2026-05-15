<?php

namespace App\Http\Requests\Super;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
        $superAdminId = (int) ($this->user('super_admin')?->id ?? 0);

        return [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:super_admins,email,' . $superAdminId],
            'profil' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => ['nullable', 'required_with:password', 'current_password:super_admin'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
