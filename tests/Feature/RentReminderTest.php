<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rent is 1,000, due on the 1st (moved in Aug 1). September to November are paid unless a test says otherwise. */
class RentReminderTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(array $paidMonths = ['09', '10', '11']): Tenant
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $tenant = Tenant::create(['user_id' => $user->id, 'status' => 'active', 'move_in_date' => '2026-08-01']);
        $room = Room::create(['room_number' => 'R'.$tenant->id, 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 1000]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-08-01', 'status' => 'active']);
        foreach ($paidMonths as $month) {
            Payment::create(['tenant_id' => $tenant->id, 'amount' => 1000, 'due_date' => "2026-$month-01", 'paid_date' => "2026-$month-01", 'status' => 'paid']);
        }

        return $tenant;
    }

    private function messages(Tenant $tenant): array
    {
        return Notification::where('user_id', $tenant->user_id)->where('type', 'rent_reminder')->orderBy('id')->pluck('message')->all();
    }

    public function test_reminders_go_out_three_days_before_and_on_the_due_day_once_each(): void
    {
        $tenant = $this->tenant();

        $this->travelTo(now()->setDate(2026, 11, 27)->setTime(7, 0)); // 4 days before: nothing yet
        $this->artisan('rent:send-reminders')->expectsOutputToContain('Sent 0')->assertSuccessful();

        $this->travelTo(now()->setDate(2026, 11, 28)->setTime(7, 0)); // 3 days before
        $this->artisan('rent:send-reminders')->expectsOutputToContain('Sent 1')->assertSuccessful();
        $this->artisan('rent:send-reminders')->expectsOutputToContain('Sent 0'); // already sent

        $this->travelTo(now()->setDate(2026, 11, 30)->setTime(7, 0)); // 1 day before: still the same reminder, not repeated
        $this->artisan('rent:send-reminders')->expectsOutputToContain('Sent 0');

        $this->travelTo(now()->setDate(2026, 12, 1)->setTime(7, 0)); // due day
        $this->artisan('rent:send-reminders')->expectsOutputToContain('Sent 1');

        $this->assertSame([
            'Reminder: your rent of ₱1,000.00 is due on Dec 1, 2026 (in 3 days).',
            'Your rent of ₱1,000.00 is due today (Dec 1, 2026).',
        ], $this->messages($tenant));
    }

    public function test_the_reminder_adds_other_unpaid_charges_and_skips_paid_rent(): void
    {
        $owes = $this->tenant(['09', '11']); // October was never paid
        $paidAhead = $this->tenant(['09', '10', '11']);
        Payment::create(['tenant_id' => $paidAhead->id, 'amount' => 1000, 'due_date' => '2026-12-01', 'paid_date' => '2026-11-20', 'status' => 'paid']);

        $this->travelTo(now()->setDate(2026, 11, 28)->setTime(7, 0));
        $this->artisan('rent:send-reminders')->assertSuccessful();

        $this->assertSame(['Reminder: your rent of ₱1,000.00 is due on Dec 1, 2026 (in 3 days). You also have ₱1,000.00 in other unpaid charges, so you owe ₱2,000.00 in total.'], $this->messages($owes));
        $this->assertSame([], $this->messages($paidAhead)); // December is already paid, so the next due is in January
    }

    public function test_opening_the_portal_sends_a_missed_reminder(): void
    {
        $tenant = $this->tenant();
        $this->travelTo(now()->setDate(2026, 11, 29)->setTime(9, 0)); // the scheduler did not run on the 28th
        $this->actingAs($tenant->user)->get(route('tenant.dashboard'))->assertOk();
        $this->actingAs($tenant->user)->get(route('tenant.dashboard'))->assertOk();

        $this->assertSame(['Reminder: your rent of ₱1,000.00 is due on Dec 1, 2026 (in 2 days).'], $this->messages($tenant));
    }

    public function test_a_reminder_links_to_my_payments(): void
    {
        $tenant = $this->tenant();
        $this->travelTo(now()->setDate(2026, 11, 28)->setTime(7, 0));
        $this->artisan('rent:send-reminders')->assertSuccessful();

        $this->actingAs($tenant->user)->get(route("notifications.index"))->assertOk()
            ->assertSee("data-notif-url=\"".route("tenant.payments.index")."\"", false)
            ->assertSee("View my payments");
    }
}
