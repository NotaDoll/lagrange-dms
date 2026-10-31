<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutstandingBalancesTest extends TestCase
{
    use RefreshDatabase;

    private function assignedTenant(string $roomNumber): Tenant
    {
        $tenant = Tenant::create(['user_id' => User::factory()->create()->id, 'move_in_date' => '2026-08-10']);
        $room = Room::create(['room_number' => $roomNumber, 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 1300]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-08-10', 'status' => 'active']);

        return $tenant;
    }

    public function test_unpaid_rent_appears_without_payment_records_and_independent_of_period_filter(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19));
        $tenant = $this->assignedTenant('101');
        $this->actingAs(User::factory()->create(['role' => 'proprietor']))
            ->get(route('proprietor.reports.index'))->assertOk()->assertSee('Current unpaid balances')
            ->assertSee($tenant->user->name)->assertSee('2,600.00')
            ->assertViewHas('currentOutstanding', 2600.0)->assertViewHas('unpaidTenantCount', 1)
            ->assertViewHas('totalOutstanding', 2600.0)->assertViewHas('totalBilled', 2600.0)->assertViewHas('totalOverdue', 2600.0)
            ->assertViewHas('balanceRows', fn ($rows) => $rows->first()->unpaid_months === 2);
        $this->get(route('proprietor.reports.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))
            ->assertOk()->assertViewHas('totalOutstanding', 0.0)->assertViewHas('currentOutstanding', 2600.0);
        $this->get(route('proprietor.reports.index', ['date_from' => '2026-08-31', 'date_to' => '2026-09-29']))
            ->assertOk()->assertViewHas('totalBilled', 1300.0)->assertViewHas('totalOutstanding', 1300.0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_partial_and_full_payments_reduce_debt_without_counting_rent_and_ledger_twice(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19));
        $tenant = $this->assignedTenant('101');
        $other = $this->assignedTenant('102');
        Payment::create(['tenant_id' => $tenant->id, 'amount' => 500, 'due_date' => '2026-08-31', 'paid_date' => '2026-09-01', 'status' => 'paid']);
        Payment::create(['tenant_id' => $tenant->id, 'amount' => 800, 'due_date' => '2026-08-31', 'status' => 'overdue']);
        $september = Payment::create(['tenant_id' => $tenant->id, 'amount' => 1300, 'due_date' => '2026-09-30', 'status' => 'overdue']);
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $this->get(route('proprietor.reports.index'))->assertOk()->assertViewHas('currentOutstanding', 4700.0)->assertViewHas('unpaidTenantCount', 2);
        $this->get(route('proprietor.reports.index', ['tenant_id' => $tenant->id]))
            ->assertOk()->assertViewHas('currentOutstanding', 2100.0)->assertViewHas('unpaidTenantCount', 1)
            ->assertViewHas('totalBilled', 2600.0)->assertViewHas('totalCollected', 500.0)->assertViewHas('totalOutstanding', 2100.0);
        $this->patch(route('proprietor.payments.settle', $september), ['paid_date' => '2026-10-19'])->assertRedirect();
        $this->get(route('proprietor.reports.index', ['tenant_id' => $tenant->id]))
            ->assertOk()->assertViewHas('currentOutstanding', 800.0)
            ->assertViewHas('balanceRows', fn ($rows) => $rows->first()->unpaid_months === 1);
        $this->get(route('proprietor.reports.index', ['tenant_id' => $other->id]))
            ->assertViewHas('currentOutstanding', 2600.0);
    }

    public function test_explicit_debts_without_assignment_are_included_but_future_charges_are_excluded(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19));
        $tenant = Tenant::create(['user_id' => User::factory()->create()->id]);
        Payment::create(['tenant_id' => $tenant->id, 'amount' => 900, 'due_date' => '2026-07-01', 'status' => 'pending']);
        Payment::create(['tenant_id' => $tenant->id, 'amount' => 400, 'due_date' => '2026-10-25', 'status' => 'pending']);
        $this->actingAs(User::factory()->create(['role' => 'proprietor']))
            ->get(route('proprietor.reports.index'))->assertOk()
            ->assertViewHas('currentOutstanding', 900.0)->assertViewHas('unpaidTenantCount', 1);
    }
}
