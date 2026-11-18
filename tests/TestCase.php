<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never reach a real service (TextBee would text real guardians, Groq would bill).
        // A test that needs one fakes it with Http::fake(); anything else fails loudly.
        Http::preventStrayRequests();
    }
}
