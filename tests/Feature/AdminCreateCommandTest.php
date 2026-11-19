<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCreateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_proprietor_login_and_rejects_a_taken_email(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('First name', 'Ana')->expectsQuestion('Last name', 'Reyes')
            ->expectsQuestion('Email (used to log in)', ' Ana.Reyes@Example.com ')
            ->expectsQuestion('Password (at least 8 characters)', 'demo-pass-1')
            ->expectsQuestion('Type the password again', 'demo-pass-1')
            ->expectsOutputToContain('Admin account created for Ana Reyes')
            ->assertSuccessful();

        $admin = User::where('email', 'ana.reyes@example.com')->sole();
        $this->assertTrue($admin->isProprietor());
        $this->assertSame('Ana Reyes', $admin->name);
        $this->assertTrue(Hash::check('demo-pass-1', $admin->password));

        $this->artisan('admin:create')
            ->expectsQuestion('First name', 'Ana')->expectsQuestion('Last name', 'Reyes')
            ->expectsQuestion('Email (used to log in)', 'ana.reyes@example.com')
            ->expectsQuestion('Password (at least 8 characters)', 'demo-pass-1')
            ->expectsQuestion('Type the password again', 'demo-pass-1')
            ->expectsOutputToContain('The email has already been taken.')
            ->assertFailed();
        $this->assertSame(1, User::where('role', 'proprietor')->count());
    }
}
