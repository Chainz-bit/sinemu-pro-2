<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
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
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Email atau username wajib diisi.',
            'login.string' => 'Email atau username harus berupa teks.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.string' => 'Kata sandi harus berupa teks.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => $this->input('login') !== null ? trim((string) $this->input('login')) : null,
            'password' => $this->input('password') !== null ? (string) $this->input('password') : null,
        ]);
    }

    public function loginInput(): string
    {
        return (string) $this->validated('login');
    }
}
