<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates a default proprietor account for local/dev setups, so a fresh
     * clone always has at least one working login without needing manual
     * tinker commands. Safe to re-run — firstOrCreate skips if it exists.
     *
     * ⚠️ Change this password before deploying anywhere real — this is a
     * known, publicly-documented default meant only for local development.
     */
    public function run(): void
    {
        $proprietor = User::firstOrCreate(
            ['email' => 'admin@lagrange.test'],
            [
                'name' => 'Merlinda Villanueva',
                'first_name' => 'Merlinda',
                'last_name' => 'Villanueva',
                'password' => Hash::make('password'),
                'role' => 'proprietor',
            ],
        );

        $this->command->info("Proprietor account ready:");
        $this->command->info("  Email:    {$proprietor->email}");
        $this->command->info("  Password: password (change this after first login)");
    }
}