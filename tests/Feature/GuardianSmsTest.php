<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\SmsLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GuardianSmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuardianSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0));
        $batch = 0;
        Http::fake(['api.textbee.dev/*' => function () use (&$batch) { // TextBee gives each message its own batch id
            return Http::response(['data' => ['success' => true, 'smsBatchId' => 'batch-'.(++$batch)]], 201);
        }]);
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
    }

    /** High risk: moved in Jul 20, rent of 1,300 due Aug 20, Sep 20 and Oct 20, none paid. */
    private function behind(string $name, ?string $guardian = 'Maria Santos', ?string $number = '09171234567'): Tenant
    {
        $tenant = Tenant::create([
            'user_id' => User::factory()->create(['name' => $name, 'role' => 'tenant'])->id, 'status' => 'active',
            'move_in_date' => '2026-07-20', 'guardian_name' => $guardian, 'guardian_contact_number' => $number,
        ]);
        $room = Room::create(['room_number' => 'R'.$tenant->id, 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 1300]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-07-20', 'status' => 'active']);

        return $tenant;
    }

    public function test_default_message_is_personalized_with_names_and_overdue_amount(): void
    {
        $tenant = $this->behind('Juan Dela Cruz');
        $filled = app(GuardianSmsMessage::class)->forTenant($tenant->fresh());

        $this->assertSame(
            "Dear Maria Santos, Juan Dela Cruz has an overdue dormitory balance of PHP 3,900.00. Kindly settle it soon. Thank you.\n- La Grange Dormitory Management",
            $filled['message']
        );
        // One text (160 characters). A peso sign instead of PHP would force Unicode and need 3.
        $stats = GuardianSmsMessage::stats($filled['message']);
        $this->assertFalse($stats['unicode']);
        $this->assertSame(1, $stats['texts']);
        $this->assertSame(3, GuardianSmsMessage::stats(str_replace('PHP ', '₱', $filled['message']))['texts']);
        $this->assertStringNotContainsString('₱', GuardianSmsMessage::DEFAULT_TEMPLATE);
        $this->assertStringNotContainsString('[Due Date]', GuardianSmsMessage::DEFAULT_TEMPLATE); // no date in the reminder
        $this->assertSame(['characters' => 26, 'texts' => 1, 'unicode' => false, 'unicode_characters' => []], GuardianSmsMessage::stats('Pay PHP 100 [x]. Thanks.')); // [ and ] count double

        // Names imported in capitals are written normally in the text.
        $caps = $this->behind('PEDRO SANTOS', guardian: 'JOELYN SANTOS');
        $this->assertStringStartsWith('Dear Joelyn Santos, Pedro Santos has an overdue', app(GuardianSmsMessage::class)->forTenant($caps->fresh())['message']);

        // Long names are shortened, keeping the surname, so the reminder still fits one text.
        $long = $this->behind('Juan Miguel Antonio Dela Cruz', guardian: 'Maria Cristina Dela Cruz-Santos');
        $longMessage = app(GuardianSmsMessage::class)->forTenant($long->fresh())['message'];
        $this->assertSame(1, GuardianSmsMessage::stats($longMessage)['texts']);
        $this->assertStringStartsWith('Dear Maria Dela Cruz-Santos, Juan Dela Cruz has an overdue', $longMessage);

        $noGuardianName = $this->behind('Ana Reyes', guardian: null);
        $this->assertStringStartsWith('Dear Parent/Guardian,', app(GuardianSmsMessage::class)->forTenant($noGuardianName->fresh())['message']);
    }

    public function test_proprietor_can_edit_the_template_in_settings_and_reset_it(): void
    {
        $this->get(route('proprietor.settings.edit'))->assertOk()
            ->assertSee('Guardian payment reminder (SMS)')->assertSee('[Tenant Name]')->assertSee('Juan Dela Cruz');

        $template = "Hi [Guardian Name], [Tenant Name] owes PHP [Amount] since [Due Date].\n- La Grange Dormitory Management";
        $this->put(route('proprietor.settings.update'), ['dormitory_name' => 'La Grange', 'sms_guardian_template' => $template])->assertSessionHasNoErrors();
        $tenant = $this->behind('Juan Dela Cruz');
        $this->assertSame("Hi Maria Santos, Juan Dela Cruz owes PHP 3,900.00 since August 20, 2026.\n- La Grange Dormitory Management",
            app(GuardianSmsMessage::class)->forTenant($tenant->fresh())['message']);

        $this->put(route('proprietor.settings.update'), ['dormitory_name' => 'La Grange', 'sms_guardian_template' => ''])->assertSessionHasNoErrors();
        $this->assertSame(GuardianSmsMessage::DEFAULT_TEMPLATE, app(GuardianSmsMessage::class)->template());
        $this->put(route('proprietor.settings.update'), ['dormitory_name' => 'La Grange', 'sms_guardian_template' => str_repeat('a', 481)])
            ->assertSessionHasErrors('sms_guardian_template');
    }

    public function test_preview_then_send_an_edited_message_to_one_guardian(): void
    {
        $tenant = $this->behind('Juan Dela Cruz');

        $this->getJson(route('proprietor.dashboard.sms.preview', $tenant))->assertOk()
            ->assertJson(['tenant_name' => 'Juan Dela Cruz', 'guardian_name' => 'Maria Santos', 'number_hint' => '•••• 4567', 'sent_today' => false])
            ->assertJsonPath('stats.texts', 1)
            ->assertJsonMissingPath('guardian_contact_number');

        $edited = "Dear Maria Santos, Juan's rent is overdue. Please call us today.\n- La Grange Dormitory Management";
        $this->postJson(route('proprietor.dashboard.sms', $tenant), ['message' => $edited])->assertOk()->assertJsonPath('status', 'queued');
        $this->assertSame($edited, SmsLog::sole()->message);
        Http::assertSent(fn ($request) => $request['message'] === $edited);

        // A second send the same day is reused, whatever the wording (no double text).
        $this->postJson(route('proprietor.dashboard.sms', $tenant), ['message' => 'Another wording'])->assertOk();
        $this->assertDatabaseCount('sms_logs', 1);
        $this->getJson(route('proprietor.dashboard.sms.preview', $tenant))->assertJson(['sent_today' => true]);
    }

    public function test_text_all_sends_one_personalized_message_per_high_risk_guardian(): void
    {
        $juan = $this->behind('Juan Dela Cruz', 'Maria Santos', '09171234567');
        $ana = $this->behind('Ana Reyes', 'Pedro Reyes', '09181112222');
        $this->behind('No Number', 'Someone', null);
        $paidUp = $this->behind('Paid Up', 'Lito Cruz', '09190001111');
        foreach (['08-20', '09-20', '10-20'] as $day) {
            Payment::create(['tenant_id' => $paidUp->id, 'amount' => 1300, 'due_date' => "2026-$day", 'paid_date' => "2026-$day", 'status' => 'paid', 'payment_type' => 'full']);
        }

        $this->get(route('proprietor.dashboard'))->assertOk()->assertSee('Text all high-risk guardians (2)');
        $preview = $this->getJson(route('proprietor.dashboard.sms.bulk-preview'))->assertOk()->json('recipients');
        $this->assertSame(['Ana Reyes', 'Juan Dela Cruz'], array_column($preview, 'tenant_name'));
        $this->assertStringStartsWith('Dear Pedro Reyes, Ana Reyes has an overdue', $preview[0]['message']);
        $this->assertStringStartsWith('Dear Maria Santos, Juan Dela Cruz has an overdue', $preview[1]['message']);

        $this->postJson(route('proprietor.dashboard.sms.bulk'), ['recipients' => [
            ['tenant_id' => $juan->id, 'message' => $preview[1]['message']],
            ['tenant_id' => $ana->id, 'message' => 'Edited for Pedro'],
            ['tenant_id' => $paidUp->id, 'message' => 'Should not go out'],
        ]])->assertOk()->assertJson(['sent' => 2, 'skipped' => 1, 'failed' => 0]);

        $this->assertSame(['Edited for Pedro', $preview[1]['message']], SmsLog::orderBy('tenant_id', 'desc')->pluck('message')->all());
        $this->assertDatabaseMissing('sms_logs', ['tenant_id' => $paidUp->id]);

        // Running it again the same day skips everyone already texted.
        $this->postJson(route('proprietor.dashboard.sms.bulk'), ['recipients' => [['tenant_id' => $juan->id, 'message' => 'Again']]])
            ->assertOk()->assertJson(['sent' => 0, 'skipped' => 1])->assertJsonPath('rows.0.reason', 'Already texted today.');
        $this->assertDatabaseCount('sms_logs', 2);
    }

    public function test_tenants_cannot_preview_or_send_guardian_texts(): void
    {
        $juan = $this->behind('Juan Dela Cruz');
        $this->actingAs($juan->user);
        $this->getJson(route('proprietor.dashboard.sms.preview', $juan))->assertForbidden();
        $this->getJson(route('proprietor.dashboard.sms.bulk-preview'))->assertForbidden();
        $this->postJson(route('proprietor.dashboard.sms.bulk'), ['recipients' => [['tenant_id' => $juan->id, 'message' => 'x']]])->assertForbidden();
        $this->assertDatabaseCount('sms_logs', 0);
    }
}
