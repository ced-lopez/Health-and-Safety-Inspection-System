<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen((string) $value) < 8) {
            $fail('The :attribute must be at least 8 characters.');
        }

        if (! preg_match('/[A-Z]/', (string) $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
        }

        if (! preg_match('/[a-z]/', (string) $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');
        }

        if (! preg_match('/[0-9]/', (string) $value)) {
            $fail('The :attribute must contain at least one number.');
        }

        if (! preg_match('/[@$!%*#?&._-]/', (string) $value)) {
            $fail('The :attribute must contain at least one special character.');
        }
    }
}
