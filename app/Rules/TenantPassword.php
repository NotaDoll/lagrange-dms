<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TenantPassword implements ValidationRule
{
    public const REQUIREMENTS = [
        [
            'pattern' => '[\s\S]{8}',
            'label' => 'At least 8 characters',
            'message' => 'Use at least 8 characters.',
        ],
        [
            'pattern' => '^(?=[\s\S]*[A-Z])(?=[\s\S]*[a-z])',
            'label' => 'An uppercase letter (A–Z) and a lowercase letter (a–z)',
            'message' => 'Include an uppercase letter (A–Z) and a lowercase letter (a–z).',
        ],
        [
            'pattern' => '[0-9]',
            'label' => 'A number (0–9)',
            'message' => 'Include a number (0–9).',
        ],
        [
            'pattern' => '[\p{P}\p{S}]',
            'label' => 'A special character such as !, @, #, $, or %',
            'message' => 'Include a special character such as !, @, #, $, or %.',
        ],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        foreach (self::REQUIREMENTS as $requirement) {
            if (preg_match('/'.$requirement['pattern'].'/u', $value) !== 1) {
                $fail($requirement['message']);
            }
        }
    }
}
