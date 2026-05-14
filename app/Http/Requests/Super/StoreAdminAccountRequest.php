<?php

namespace App\Http\Requests\Super;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class StoreAdminAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('super_admin') !== null;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash:ascii', 'unique:admins,username'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:admins,email'],
            'nomor_telepon' => ['required', 'string', 'regex:/^(08[0-9]{8,13}|\\+628[0-9]{8,13})$/'],
            'instansi' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'alamat_lengkap' => ['required', 'string', 'max:1200'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'status_verifikasi' => ['required', Rule::in(['pending', 'active', 'rejected', 'inactive'])],
            'alasan_penolakan' => ['nullable', 'string', 'max:1000', 'required_if:status_verifikasi,rejected'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nama' => $this->filled('nama') ? trim((string) $this->input('nama')) : null,
            'username' => $this->filled('username') ? trim((string) $this->input('username')) : null,
            'email' => $this->filled('email') ? trim((string) $this->input('email')) : null,
            'nomor_telepon' => $this->filled('nomor_telepon') ? trim((string) $this->input('nomor_telepon')) : null,
            'instansi' => $this->filled('instansi') ? trim((string) $this->input('instansi')) : null,
            'kecamatan' => $this->filled('kecamatan') ? trim((string) $this->input('kecamatan')) : null,
            'alamat_lengkap' => $this->filled('alamat_lengkap') ? trim((string) $this->input('alamat_lengkap')) : null,
            'password' => $this->input('password') !== null ? (string) $this->input('password') : null,
            'password_confirmation' => $this->input('password_confirmation') !== null ? (string) $this->input('password_confirmation') : null,
        ]);
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'nomor_telepon.regex' => 'Nomor telepon harus menggunakan format 08xxxxxxxxxx atau +628xxxxxxxxxx.',
        ];
    }
}
