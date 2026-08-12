<?php

namespace App\Services;

use Illuminate\Support\Str;

class TempPasswordGenerator
{
    /**
     * Format: LastName + ContactNumber (e.g. "Royo09123456789").
     * Falls back to a random password if no contact number is available,
     * since there's nothing predictable to build one from.
     */
    public function fromNameParts(string $lastName, ?string $contactNumber): string
    {
        if (empty($contactNumber)) {
            return strtoupper(Str::random(10));
        }

        $digitsOnly = preg_replace('/\D/', '', $contactNumber);

        return strtoupper(trim($lastName)) . $digitsOnly;
    }

    public function fromFullName(string $fullName, ?string $contactNumber): string
    {
        $nameParts = preg_split('/\s+/', trim($fullName));
        $lastName = end($nameParts);

        return $this->fromNameParts($lastName, $contactNumber);
    }
}