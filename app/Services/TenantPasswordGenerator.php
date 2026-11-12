<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantPasswordGenerator
{
    public function generate(string $lastName, string $mobile): string
    {
        return $this->validatedPrefix($lastName, $mobile).$mobile;
    }

    public function validate(string $lastName, string $mobile): void
    {
        $this->validatedPrefix($lastName, $mobile);
    }

    private function validatedPrefix(string $lastName, string $mobile): string
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($lastName)));

        if ($letters === '' || (config('hashing.driver') === 'bcrypt' && strlen($letters) + strlen($mobile) > 72)) {
            throw ValidationException::withMessages([
                'last_name' => 'The last name must contain letters and fit in the initial login password.',
            ]);
        }

        return $letters;
    }
}
