<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_proprietor_can_create_tenant_without_password_fields(): void
    {
        $proprietor = User::factory()->create(['role' => 'proprietor']);

        $this->actingAs($proprietor)
            ->post(route('proprietor.tenants.store'), [
                'first_name' => 'Ana',
                'middle_name' => 'Santos',
                'last_name' => 'Royo',
                'email' => 'ana.royo@example.com',
                'contact_number' => '09123456789',
                'emergency_contact_name' => 'Maria Royo',
                'emergency_contact_number' => '09987654321',
                'guardian_name' => null,
                'guardian_contact_number' => null,
                'move_in_date' => '2026-07-03',
                'status' => 'active',
                'bed_id' => null,
            ])
            ->assertRedirect(route('proprietor.tenants.index'))
            ->assertSessionHas('success', 'Tenant created successfully.')
            ->assertSessionHas('temp_password', 'Royo09123456789');

        $this->assertDatabaseHas('users', [
            'name' => 'Ana Santos Royo',
            'email' => 'ana.royo@example.com',
            'role' => 'tenant',
            'contact_number' => '09123456789',
        ]);

        $this->assertDatabaseHas('tenants', [
            'emergency_contact_name' => 'Maria Royo',
            'emergency_contact_number' => '09987654321',
            'status' => 'active',
        ]);
    }

    public function test_tenant_cannot_access_proprietor_tenant_list(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->actingAs($tenant)
            ->get(route('proprietor.tenants.index'))
            ->assertForbidden();
    }
}
