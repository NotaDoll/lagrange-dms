<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComplaintUrgencyTest extends TestCase
{
    use RefreshDatabase;

    private function answer(bool $urgent, string $reason = 'Sparking outlet is a fire risk'): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['urgent' => $urgent, 'reason' => $reason])]]]])]);
    }

    private function tenantUser(): User
    {
        $user = User::factory()->create(['role' => 'tenant']);
        Tenant::create(['user_id' => $user->id, 'status' => 'active', 'move_in_date' => '2026-08-01']);

        return $user;
    }

    public function test_a_new_complaint_is_checked_once_and_shows_as_urgent_for_the_proprietor(): void
    {
        config(['services.groq.key' => 'test-key']);
        $this->answer(true);

        $this->actingAs($this->tenantUser())->post(route('tenant.complaints.store'), [
            'category' => 'maintenance', 'description' => 'Nag-spark ang outlet sa Room 3',
        ])->assertRedirect();

        $complaint = Complaint::sole();
        $this->assertSame(['urgent', 'Sparking outlet is a fire risk'], [$complaint->priority, $complaint->priority_reason]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['response_format'] === ['type' => 'json_object']);

        // Already checked: running the command again sends nothing.
        $this->artisan('complaints:check-urgency')->expectsOutputToContain('already been checked')->assertSuccessful();
        Http::assertSentCount(1);

        $this->actingAs(User::factory()->create(['role' => 'proprietor']))
            ->get(route('proprietor.complaints.index'))->assertOk()
            ->assertSee('Marked urgent by AI: Sparking outlet is a fire risk')
            ->assertSee('data-attention="1"', false);
    }

    public function test_when_the_ai_fails_the_complaint_is_still_saved_and_left_unchecked(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake(['api.groq.com/*' => Http::sequence()
            ->push(['error' => 'rate limited'], 429) // on submit
            ->push(['choices' => [['message' => ['content' => json_encode(['urgent' => false, 'reason' => ''])]]]])]); // on retry

        $this->actingAs($this->tenantUser())->post(route('tenant.complaints.store'), [
            'category' => 'noise', 'description' => 'Music too loud last night',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertNull(Complaint::sole()->priority);

        // The command retries only unchecked complaints.
        $this->artisan('complaints:check-urgency')->expectsOutputToContain('1 complaint(s): 0 urgent, 1 normal')->assertSuccessful();
        $this->assertSame('normal', Complaint::sole()->priority);
    }
}
