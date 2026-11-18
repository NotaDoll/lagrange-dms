<?php

namespace Tests\Feature;

use App\Models\SmsLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsStatusRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_refresh_asks_textbee_and_returns_todays_status_in_philippine_time(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0)); // 10:00 Manila
        $tenant = Tenant::create(['user_id' => User::factory()->create(['role' => 'tenant'])->id, 'status' => 'active', 'guardian_contact_number' => '09171234567']);
        $log = SmsLog::create(['tenant_id' => $tenant->id, 'recipient_number' => '09171234567', 'recipient_type' => 'guardian',
            'message' => 'Dear Maria, ...', 'status' => 'queued', 'textbee_batch_id' => 'batch-9']);
        Http::fake(['api.textbee.dev/*' => Http::response(['data' => [['status' => 'delivered', 'sentAt' => '2026-10-31T01:30:00Z']]], 200)]);

        $this->actingAs(User::factory()->create(['role' => 'proprietor']))
            ->postJson(route('proprietor.dashboard.sms.refresh'))->assertOk()
            ->assertJson(['updated' => 1, 'statuses' => [$tenant->id => 'delivered'], 'error' => null]);

        // TextBee reports UTC; it is saved in the app's Philippine time (01:30 UTC = 09:30 Manila).
        $this->assertSame(['delivered', '2026-10-31 09:30'], [$log->fresh()->status, $log->fresh()->sent_at->format('Y-m-d H:i')]);
        $this->assertSame('Asia/Manila', config('app.timezone'));
    }

    public function test_refresh_reports_a_textbee_problem_without_failing_the_page(): void
    {
        $tenant = Tenant::create(['user_id' => User::factory()->create(['role' => 'tenant'])->id, 'status' => 'active']);
        SmsLog::create(['tenant_id' => $tenant->id, 'recipient_number' => '09171234567', 'recipient_type' => 'guardian',
            'message' => 'x', 'status' => 'queued', 'textbee_batch_id' => 'batch-1']);
        Http::fake(['api.textbee.dev/*' => Http::response([], 401)]);

        $this->actingAs(User::factory()->create(['role' => 'proprietor']))
            ->postJson(route('proprietor.dashboard.sms.refresh'))->assertOk()
            ->assertJsonPath('statuses.'.$tenant->id, 'queued')
            ->assertJsonPath('error', 'TextBee rejected access. Check the API key and account permissions.');
    }
}
