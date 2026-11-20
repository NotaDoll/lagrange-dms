<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Today" is Nov 21, 2026. Rent is 1,000 and falls due on the tenant's move-in day each month. */
class TenantNextDueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(10, 0));
    }

    private function tenant(string $moveIn, array $paidMonths): Tenant
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $tenant = Tenant::create(['user_id' => $user->id, 'status' => 'active', 'move_in_date' => $moveIn]);
        $room = Room::create(['room_number' => 'R'.$tenant->id, 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 1000]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => $moveIn, 'status' => 'active']);
        $day = substr($moveIn, 8, 2);
        foreach ($paidMonths as $month) {
            Payment::create(['tenant_id' => $tenant->id, 'amount' => 1000, 'due_date' => "2026-$month-$day", 'paid_date' => "2026-$month-$day", 'status' => 'paid']);
        }

        return $tenant;
    }

    private function nextDue(Tenant $tenant): array
    {
        $next = app(TenantBalanceService::class)->nextDue($tenant->fresh());

        return [$next['date']->toDateString(), $next['amount'], $next['overdue']];
    }

    public function test_this_month_paid_shows_next_month(): void
    {
        $this->assertSame(['2026-12-01', 1000.0, false], $this->nextDue($this->tenant('2026-08-01', ['09', '10', '11'])));
    }

    public function test_this_month_not_yet_due_shows_this_month(): void
    {
        $this->assertSame(['2026-11-25', 1000.0, false], $this->nextDue($this->tenant('2026-08-25', ['09', '10'])));
    }

    public function test_this_month_unpaid_and_past_is_overdue_and_shown_in_the_portal(): void
    {
        $tenant = $this->tenant('2026-08-01', ['09', '10']);
        $this->assertSame(['2026-11-01', 1000.0, true], $this->nextDue($tenant));

        $this->actingAs($tenant->user)->get(route('tenant.dashboard'))->assertOk()
            ->assertSee('Rent due Nov 1, 2026 · overdue · ₱1,000.00');
        $paidUp = $this->tenant('2026-08-01', ['09', '10', '11']);
        $this->actingAs($paidUp->user)->get(route('tenant.payments.index'))->assertOk()
            ->assertSee('Dec 1, 2026')->assertSee('in 10 days')->assertDontSee('overdue ·');
    }
}
