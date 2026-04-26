<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLostItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }
}
