<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\SmsLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationsAndSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_count_endpoint_returns_number_of_unread_notifications(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'announcement',
            'message' => 'Test message',
            'is_read' => false,
        ]);

        $this->actingAs($user)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertExactJson(['count' => 1]);
    }

    public function test_payment_recording_creates_payment_confirmation_notification(): void
    {
        $proprietor = User::factory()->create(['role' => 'proprietor']);
        $tenantUser = User::factory()->create(['role' => 'tenant']);
        $tenant = Tenant::create([
            'user_id' => $tenantUser->id,
            'status' => 'active',
            'move_in_date' => '2026-07-01',
        ]);
        $room = \App\Models\Room::create(['room_number' => 'N1', 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 1200]);
        $bed = \App\Models\Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        \App\Models\RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-07-01', 'status' => 'active']);

        $this->actingAs($proprietor)
            ->post(route('proprietor.payments.store'), [
                'tenant_id' => $tenant->id,
                'amount' => 1200,
                'due_date' => '2026-07-31',
                'paid_date' => '2026-07-01',
                'payment_type' => 'full',
                'receipt_number' => 'OR-1',
            ])
            ->assertRedirect(route('proprietor.payments.index'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $tenantUser->id,
            'type' => 'payment_confirmation',
        ]);
    }

    public function test_risk_evaluation_never_texts_anyone(): void
    {
        // Guardian reminders are reviewed and sent by the proprietor from the Dashboard, never automatically.
        Http::fake();

        $tenant = Tenant::create([
            'user_id' => User::factory()->create(['role' => 'tenant', 'contact_number' => '09171234567'])->id,
            'guardian_name' => 'Guardian',
            'guardian_contact_number' => '09998887777',
            'status' => 'active',
        ]);
        foreach (['2026-06-01' => 10, '2026-07-01' => 12] as $due => $days) {
            Payment::create(['tenant_id' => $tenant->id, 'amount' => 1000, 'due_date' => Carbon::parse($due), 'days_overdue' => $days, 'status' => 'overdue']);
        }

        $evaluation = app(PaymentRiskService::class)->evaluate($tenant);

        $this->assertSame('high', $evaluation['risk_level']);
        $this->assertSame(0, SmsLog::count());
        Http::assertNothingSent();
    }
}
