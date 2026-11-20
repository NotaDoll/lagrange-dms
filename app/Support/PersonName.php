<?php

namespace App\Support;

/**
 * Writes a person's name formally: each word capitalized ("JUAN dela cruz" → "Juan Dela Cruz"),
 * including after hyphens and apostrophes ("de la peña-cruz" → "De La Peña-Cruz"), with
 * generational suffixes kept as written by convention ("jr" → "Jr.", "iii" → "III").
 */
class PersonName
{
    private const SUFFIXES = ['jr' => 'Jr.', 'jr.' => 'Jr.', 'sr' => 'Sr.', 'sr.' => 'Sr.', 'ii' => 'II', 'iii' => 'III', 'iv' => 'IV'];

    public static function format(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name === '') {
            return '';
        }

        return collect(explode(' ', $name))->map(function (string $word) {
            $lower = mb_strtolower($word);
            if (isset(self::SUFFIXES[$lower])) {
                return self::SUFFIXES[$lower];
            }

            // Capitalize the first letter of the word and of every part after a hyphen or apostrophe.
            return preg_replace_callback("/(^|[-'’])(\p{L})/u", fn ($m) => $m[1].mb_strtoupper($m[2]), $lower);
        })->implode(' ');
    }
}
