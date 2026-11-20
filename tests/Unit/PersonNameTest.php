<?php

namespace Tests\Unit;

use App\Support\PersonName;
use PHPUnit\Framework\TestCase;

class PersonNameTest extends TestCase
{
    public function test_names_are_written_formally(): void
    {
        $this->assertSame('Barbie Angel Joy Royo', PersonName::format('BARBIE ANGEL JOY ROYO'));
        $this->assertSame('Juan Dela Cruz', PersonName::format('juan  dela cruz '));
        $this->assertSame('De La Peña-Cruz', PersonName::format('de la peña-cruz'));
        $this->assertSame("Maria O'Neil Jr.", PersonName::format("MARIA O'NEIL JR"));
        $this->assertSame('Roxette Quins III', PersonName::format('roxette quins iii'));
        $this->assertNull(PersonName::format(null));
    }
}
