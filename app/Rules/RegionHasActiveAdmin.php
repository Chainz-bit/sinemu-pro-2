<?php

namespace App\Rules;

use App\Models\Admin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RegionHasActiveAdmin implements ValidationRule
{
    public const MESSAGE = 'Wilayah ini belum memiliki pengelola aktif. Silakan pilih wilayah lain atau hubungi admin.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value)) {
            return;
        }

        $hasActiveAdmin = Admin::query()
            ->where('region_id', (int) $value)
            ->where('status_verifikasi', Admin::STATUS_ACTIVE)
            ->exists();

        if (!$hasActiveAdmin) {
            $fail(self::MESSAGE);
        }
    }
}
