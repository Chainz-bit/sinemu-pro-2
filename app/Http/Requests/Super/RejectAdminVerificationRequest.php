<?php

namespace App\Http\Requests\Super;

use Illuminate\Foundation\Http\FormRequest;

class RejectAdminVerificationRequest extends FormRequest
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
        return [
            'alasan_penolakan' => ['nullable', 'string', 'max:1200'],
        ];
    }
}
