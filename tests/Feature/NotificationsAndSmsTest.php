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
        ]);

        $this->actingAs($proprietor)
            ->post(route('proprietor.payments.store'), [
                'tenant_id' => $tenant->id,
                'amount' => 1200,
                'due_date' => '2026-07-01',
                'paid_date' => '2026-07-01',
                'status' => 'paid',
            ])
            ->assertRedirect(route('proprietor.payments.index'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $tenantUser->id,
            'type' => 'payment_confirmation',
        ]);
    }

    public function test_high_risk_tenants_receive_sms_notifications(): void
    {
        Http::fake([
            'api.semaphore.co/*' => Http::response(['message_id' => 1], 200),
        ]);

        $tenantUser = User::factory()->create([
            'role' => 'tenant',
            'contact_number' => '09171234567',
        ]);
        $tenant = Tenant::create([
            'user_id' => $tenantUser->id,
            'guardian_name' => 'Guardian',
            'guardian_contact_number' => '09998887777',
            'status' => 'active',
        ]);

        Payment::create([
            'tenant_id' => $tenant->id,
            'amount' => 1000,
            'due_date' => Carbon::parse('2026-06-01'),
            'paid_date' => null,
            'days_overdue' => 10,
            'status' => 'overdue',
        ]);

        Payment::create([
            'tenant_id' => $tenant->id,
            'amount' => 1000,
            'due_date' => Carbon::parse('2026-07-01'),
            'paid_date' => null,
            'days_overdue' => 12,
            'status' => 'overdue',
        ]);

        app(PaymentRiskService::class)->calculateForTenant($tenant);

        $this->assertSame(2, SmsLog::count());
        $this->assertDatabaseHas('sms_logs', [
            'tenant_id' => $tenant->id,
            'recipient_number' => '09171234567',
            'recipient_type' => 'tenant',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('sms_logs', [
            'tenant_id' => $tenant->id,
            'recipient_number' => '09998887777',
            'recipient_type' => 'guardian',
            'status' => 'sent',
        ]);
    }
}
