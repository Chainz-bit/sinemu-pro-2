<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class AdminRegisterRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:admins,email'],
            'nomor_telepon' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:255'],
            'instansi' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'alamat_lengkap' => ['required', 'string', 'max:1200'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'password' => $this->input('password') !== null ? (string) $this->input('password') : null,
            'password_confirmation' => $this->input('password_confirmation') !== null ? (string) $this->input('password_confirmation') : null,
            'nomor_telepon' => $this->input('nomor_telepon') !== null ? trim((string) $this->input('nomor_telepon')) : null,
        ]);
    }
}
