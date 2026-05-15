<?php

namespace App\Http\Requests\Admin;

use App\Support\IndramayuDistricts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'nomor_telepon' => ['required', 'string', 'regex:/^(08[0-9]{8,13}|\+628[0-9]{8,13})$/'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:admins,username'],
            'instansi' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', 'max:100', Rule::in(IndramayuDistricts::names())],
            'alamat_lengkap' => ['required', 'string', 'max:1200'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.unique' => 'Username sudah digunakan.',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, strip, dan underscore.',
            'email.unique' => 'Email sudah digunakan sebagai akun pengelola.',
            'nomor_telepon.regex' => 'Nomor telepon harus menggunakan format 08xxxxxxxxxx atau +628xxxxxxxxxx.',
            'kecamatan.in' => 'Kecamatan yang dipilih tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'password' => $this->input('password') !== null ? (string) $this->input('password') : null,
            'password_confirmation' => $this->input('password_confirmation') !== null ? (string) $this->input('password_confirmation') : null,
            'email' => $this->input('email') !== null ? trim((string) $this->input('email')) : null,
            'nomor_telepon' => $this->input('nomor_telepon') !== null ? trim((string) $this->input('nomor_telepon')) : null,
            'username' => $this->input('username') !== null ? trim((string) $this->input('username')) : null,
        ]);
    }
}
