<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantBalanceService;
use App\Services\TenantManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTransferRentTest extends TestCase
{
    use RefreshDatabase;

    private function bed(string $number, int $rate): Bed
    {
        $room = Room::create(['room_number' => $number, 'floor' => '1', 'capacity' => 1, 'monthly_rate' => $rate]);

        return Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
    }

    private function tenant(Bed $bed): Tenant
    {
        $tenant = Tenant::create(['user_id' => User::factory()->create(['role' => 'tenant'])->id, 'move_in_date' => '2026-08-10']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-08-10', 'status' => 'active']);

        return $tenant;
    }

    private function transfer(Tenant $tenant, ?Bed $bed): void
    {
        app(TenantManagementService::class)->update($tenant, [
            'first_name' => 'Transfer', 'last_name' => 'Tenant', 'email' => $tenant->user->email,
            'emergency_contact_name' => 'Guardian', 'emergency_contact_number' => '09123456789',
            'move_in_date' => '2026-08-10', 'status' => 'active', 'bed_id' => $bed?->id,
        ]);
    }

    public function test_current_rent_updates_immediately_but_previous_months_keep_old_rent(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19));
        $tenant = $this->tenant($this->bed('OLD', 1300));
        $new = $this->bed('NEW', 1500);
        $service = app(TenantBalanceService::class);
        $this->actingAs($tenant->user)->get(route('tenant.dashboard'))->assertOk()
            ->assertViewHas('rentTerms', fn ($terms) => $terms['monthly_rate'] === 1300.0);
        $this->transfer($tenant, $new);
        foreach (['tenant.dashboard', 'tenant.payments.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('1,500.00')
                ->assertViewHas('rentTerms', fn ($terms) => $terms['room'] === 'NEW' && $terms['monthly_rate'] === 1500.0
                    && $terms['effective_from']->toDateString() === '2026-10-19')
                ->assertViewHas('outstandingBalance', 2600.0);
        }
        $this->travelTo(now()->setDate(2026, 10, 31));
        $this->assertSame(4100.0, $service->summary($tenant->fresh())['outstanding_balance']);
        $this->actingAs(User::factory()->create(['role' => 'proprietor']))
            ->get(route('proprietor.reports.index'))->assertOk()->assertViewHas('totalBilled', 4100.0);
    }

    public function test_latest_transfer_wins_when_multiple_assignments_start_on_the_same_day(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31));
        $tenant = $this->tenant($this->bed('OLD', 1300));
        $this->transfer($tenant, $this->bed('FIRST', 1500));
        $this->transfer($tenant, $this->bed('LATEST', 1800));
        $service = app(TenantBalanceService::class);
        $this->assertSame(1800.0, $service->currentRent($tenant)['monthly_rate']);
        $this->assertSame(4400.0, $service->summary($tenant->fresh())['outstanding_balance']);
        $this->actingAs($tenant->user)->get(route('tenant.payments.index'))->assertOk()
            ->assertViewHas('rentTerms', fn ($terms) => $terms['room'] === 'LATEST')
            ->assertViewHas('outstandingBalance', 4400.0);
    }

    public function test_unassigned_tenant_retains_existing_debt_without_showing_an_active_rent(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19));
        $tenant = $this->tenant($this->bed('OLD', 1300));
        $this->transfer($tenant, null);
        $this->actingAs($tenant->user)->get(route('tenant.dashboard'))->assertOk()
            ->assertSee('No room is currently assigned.')
            ->assertViewHas('rentTerms', null)->assertViewHas('outstandingBalance', 2600.0);
    }
}
