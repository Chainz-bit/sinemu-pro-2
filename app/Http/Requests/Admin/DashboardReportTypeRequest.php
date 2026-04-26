<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardReportTypeRequest extends FormRequest
{
    public const TYPE_LOST = 'hilang';
    public const TYPE_FOUND = 'temuan';
    public const TYPE_CLAIM = 'klaim';

    /**
     * @var array<int, string>
     */
    public const ALLOWED_TYPES = [
        self::TYPE_LOST,
        self::TYPE_FOUND,
        self::TYPE_CLAIM,
    ];

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
            'type' => ['required', 'string', Rule::in(self::ALLOWED_TYPES)],
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'type' => $this->route('type'),
            'id' => $this->route('id'),
        ]);
    }

    public function reportType(): string
    {
        $type = (string) $this->route('type');

        return in_array($type, self::ALLOWED_TYPES, true) ? $type : self::TYPE_LOST;
    }
}
