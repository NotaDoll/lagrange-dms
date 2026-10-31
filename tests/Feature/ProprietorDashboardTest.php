<?php

namespace Tests\Feature;

use App\Models\AiSummary;
use App\Models\Bed;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\RiskFlag;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProprietorDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(12, 0));
        Http::preventStrayRequests();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'proprietor']);
        $this->actingAs($user);

        return $user;
    }

    private function tenant(string $name = 'Follow Up'): Tenant
    {
        return Tenant::create(['user_id' => User::factory()->create(['name' => $name, 'role' => 'tenant', 'contact_number' => '09171234567'])->id, 'status' => 'active', 'move_in_date' => '2026-08-01', 'guardian_contact_number' => '09998887777']);
    }

    private function late(Tenant $tenant, int $days = 10): Payment
    {
        return Payment::create(['tenant_id' => $tenant->id, 'amount' => 1000, 'due_date' => '2026-10-01', 'days_overdue' => $days, 'status' => 'overdue']);
    }

    public function test_dashboard_matches_rooms_financial_reports_and_open_complaints_without_side_effects(): void
    {
        $this->admin();
        $tenant = $this->tenant();
        $room = Room::create(['room_number' => '101', 'floor' => '1', 'capacity' => 2, 'monthly_rate' => 1300]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        Bed::create(['room_id' => $room->id, 'bed_label' => 'B', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-08-01', 'status' => 'active']);
        $this->late($tenant, 12);
        Payment::create(['tenant_id' => $tenant->id, 'amount' => 300, 'due_date' => '2026-10-01', 'paid_date' => '2026-10-01', 'status' => 'paid']);
        $complaint = Complaint::create(['tenant_id' => $tenant->id, 'category' => 'maintenance', 'description' => 'Water supply interrupted', 'status' => 'submitted']);
        $complaint->forceFill(['created_at' => now()->subDays(8)])->save();
        Complaint::create(['tenant_id' => $tenant->id, 'category' => 'noise', 'description' => 'Late night noise', 'status' => 'in_progress']);
        Complaint::create(['tenant_id' => $tenant->id, 'category' => 'other', 'description' => 'Resolved', 'status' => 'resolved']);
        $dashboard = $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('Dashboard')->assertSee('Send SMS');
        $rooms = $this->get(route('proprietor.rooms.index'))->assertOk();
        $report = $this->get(route('proprietor.reports.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))->assertOk();
        $allReport = $this->get(route('proprietor.reports.index', ['date_from' => '2026-08-01', 'date_to' => '2026-10-31']))->assertOk();
        $dashboard->assertViewHas('totalBeds', $rooms->viewData('totalBeds'))->assertViewHas('occupiedBeds', $rooms->viewData('occupiedBeds'))
            ->assertViewHas('availableBeds', $rooms->viewData('vacantBeds'))
            ->assertViewHas('expectedThisMonth', $report->viewData('totalBilled'))
            ->assertViewHas('collectedThisMonth', $report->viewData('totalCollected'))
            ->assertViewHas('overdueBalance', $allReport->viewData('totalOverdue'))
            ->assertViewHas('openComplaints', 2)->assertViewHas('oldComplaints', 1)->assertViewHas('newComplaintsToday', 1)
            ->assertViewHas('followUps', fn ($rows) => $rows->first()->rules_tripped === 2 && $rows->first()->risk_level === 'high');
        Http::assertNothingSent();
        $this->assertDatabaseCount('risk_flags', 0);
        $this->assertDatabaseCount('sms_logs', 0);
    }

    public function test_settings_threshold_changes_immediately_change_follow_up_membership(): void
    {
        $this->admin();
        $tenant = $this->tenant();
        $this->late($tenant);
        $this->get(route('proprietor.dashboard'))->assertViewHas('highRiskCount', 1);
        $this->put(route('proprietor.settings.update'), [
            'dormitory_name' => 'La Grange', 'risk_late_payments' => 20, 'risk_consecutive_months' => 20,
            'risk_average_days' => 100, 'risk_recent_default_days' => 1,
        ])->assertRedirect();
        $this->get(route('proprietor.dashboard'))->assertOk()->assertViewHas('followUps', fn ($rows) => $rows->isEmpty());
        $this->get(route('proprietor.settings.edit'))->assertOk()->assertSee('Payment risk thresholds');
    }

    public function test_manual_sms_uses_existing_sender_is_idempotent_and_never_sends_to_guardian(): void
    {
        $this->admin();
        Http::fake(['api.semaphore.co/*' => Http::response(['message_id' => 1], 200)]);
        $tenant = $this->tenant();
        $this->late($tenant);
        $this->postJson(route('proprietor.dashboard.sms', $tenant))->assertOk()->assertJsonPath('sent', true);
        $this->postJson(route('proprietor.dashboard.sms', $tenant))->assertOk();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('sms_logs', 1);
        $this->assertDatabaseHas('sms_logs', ['tenant_id' => $tenant->id, 'recipient_type' => 'tenant', 'status' => 'sent']);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('SMS sent');
        $low = $this->tenant('Low risk');
        $this->postJson(route('proprietor.dashboard.sms', $low))->assertUnprocessable();
    }

    public function test_failed_sms_is_logged_and_can_be_retried(): void
    {
        $this->admin();
        Http::fake(['api.semaphore.co/*' => Http::response([], 503)]);
        $tenant = $this->tenant();
        $this->late($tenant);
        $this->postJson(route('proprietor.dashboard.sms', $tenant))->assertStatus(502)->assertJsonPath('sent', false);
        $this->assertDatabaseHas('sms_logs', ['status' => 'failed']);
        $this->get(route('proprietor.dashboard'))->assertViewHas('followUps', fn ($rows) => ! $rows->first()->sms_sent);
    }

    public function test_summary_is_generated_only_on_request_and_saves_structured_counts(): void
    {
        $admin = $this->admin();
        $tenant = $this->tenant();
        Complaint::create(['tenant_id' => $tenant->id, 'category' => 'maintenance', 'description' => 'No water supply', 'status' => 'submitted']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['themes' => [['theme' => 'Water supply interruptions', 'count' => 1]], 'recommended_action' => 'Inspect the water supply.'])]]]], 200)]);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('Generate new summary')
            ->assertDontSee('No saved complaint summary')->assertDontSee('No complaint summary has been saved')
            ->assertDontSee('AI SUMMARY')->assertDontSee('AI-generated')
            ->assertDontSee('May contain inaccuracies. Review before taking action.');
        Http::assertNothingSent();
        $this->postJson(route('proprietor.dashboard.summary'))->assertOk();
        $this->assertDatabaseCount('ai_summaries', 1);
        $this->assertSame($admin->id, AiSummary::sole()->generated_by);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('Water supply interruptions')->assertSee('Inspect the water supply.')
            ->assertSee('May contain inaccuracies. Review before taking action.');
        Http::assertSentCount(1);
    }

    public function test_summary_errors_preserve_old_summary_and_do_not_save_invalid_counts(): void
    {
        $admin = $this->admin();
        $tenant = $this->tenant();
        AiSummary::create(['generated_by' => $admin->id, 'filter_params' => [], 'prompt_used' => 'Legacy prompt', 'summary_result' => 'Existing saved summary', 'response_time_seconds' => 1]);
        Complaint::create(['tenant_id' => $tenant->id, 'category' => 'other', 'description' => 'One complaint', 'status' => 'submitted']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '{"themes":[{"theme":"Invented","count":99}],"recommended_action":"Inspect"}']]]], 200)]);
        $this->postJson(route('proprietor.dashboard.summary'))->assertStatus(502);
        $this->assertDatabaseCount('ai_summaries', 1);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('Existing saved summary');
    }

    public function test_reserved_rooms_notifications_and_risk_history_use_saved_records(): void
    {
        $admin = $this->admin();
        $tenant = $this->tenant();
        $this->late($tenant);
        RiskFlag::create(['tenant_id' => $tenant->id, 'risk_level' => 'medium', 'calculated_at' => '2026-09-01']);
        Room::create(['room_number' => '201', 'floor' => '2', 'capacity' => 1, 'monthly_rate' => 1300, 'status' => 'reserved']);
        $admin->notifications()->create(['type' => 'announcement', 'message' => 'Test', 'is_read' => false]);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertViewHas('reservedRooms', 1)->assertViewHas('unreadCount', 1)
            ->assertSee('Sep: medium')->assertViewHas('upcomingMoveIns', 0);
    }

    public function test_guest_and_tenant_cannot_access_dashboard_or_actions(): void
    {
        $tenant = $this->tenant();
        $this->get(route('proprietor.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($tenant->user)->get(route('proprietor.dashboard'))->assertForbidden();
        $this->postJson(route('proprietor.dashboard.sms', $tenant))->assertForbidden();
        $this->postJson(route('proprietor.dashboard.summary'))->assertForbidden();
    }

    public function test_fifty_tenant_dashboard_has_bounded_queries_and_loads_under_three_seconds(): void
    {
        $this->admin();
        $room = Room::create(['room_number' => 'PERF', 'floor' => '1', 'capacity' => 50, 'monthly_rate' => 1300]);
        for ($index = 0; $index < 50; $index++) {
            $tenant = $this->tenant('Tenant '.$index);
            $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'Bed '.$index, 'status' => 'available']);
            RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-08-01', 'status' => 'active']);
            $this->late($tenant);
        }
        DB::enableQueryLog();
        $started = microtime(true);
        $this->get(route('proprietor.dashboard'))->assertOk()->assertViewHas('occupiedBeds', 50)->assertViewHas('highRiskCount', 50);
        $elapsed = microtime(true) - $started;
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(3.0, $elapsed, '50-tenant dashboard render exceeded 3 seconds.');
        $this->assertLessThan(40, $queries, 'Dashboard has unexpected per-tenant queries.');
        Http::assertNothingSent();
    }
}
