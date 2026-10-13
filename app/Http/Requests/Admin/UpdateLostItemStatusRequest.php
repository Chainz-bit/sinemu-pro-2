<?php

namespace App\Http\Requests\Admin;

use App\Support\ManagerPortal;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLostItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user(ManagerPortal::guard()) !== null;
    }
}
