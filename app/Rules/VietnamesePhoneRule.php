<?php

namespace App\Rules;

use App\Support\VietnamesePhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class VietnamesePhoneRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! VietnamesePhone::isValid(is_string($value) ? $value : null)) {
            $fail('Số điện thoại Việt Nam không hợp lệ.');
        }
    }
}
