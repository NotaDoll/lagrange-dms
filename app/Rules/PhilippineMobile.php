<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhilippineMobile implements ValidationRule
{
    public function __construct(private readonly ?string $existingNumber = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ($value === $this->existingNumber || self::normalize($value) !== null)) {
            return;
        }

        $fail('Enter a Philippine mobile number in 09XXXXXXXXX, +639XXXXXXXXX, or 639XXXXXXXXX format.');
    }

    public static function normalize(?string $number, bool $allowMissingZero = false): ?string
    {
        if ($number === null) {
            return null;
        }

        $compact = preg_replace('/[\s-]+/u', '', $number);

        if ($allowMissingZero && $compact !== null && preg_match('/^9[0-9]{9}$/D', $compact)) {
            $compact = '0'.$compact;
        }

        return $compact !== null && preg_match('/^(?:0|\+?63)(9[0-9]{9})$/D', $compact, $matches)
            ? '0'.$matches[1]
            : null;
    }
}
