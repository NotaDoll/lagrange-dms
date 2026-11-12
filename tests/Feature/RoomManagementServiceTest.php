<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoomAvailability;
use App\Services\TenantBalanceService;
use App\Services\RoomManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_beds_with_letter_labels(): void
    {
        $service = new RoomManagementService();

        $service->create([
            'room_number' => '101',
            'capacity' => 3,
            'monthly_rate' => 1500.00,
        ]);

        $room = Room::latest('id')->first();

        $this->assertSame(['A', 'B', 'C'], $room->beds()->pluck('bed_label')->all());
        $this->assertSame('Unspecified', $room->floor);
    }

    public function test_room_management_can_create_and_edit_without_a_floor_field(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $attributes = ['room_number' => '101', 'capacity' => 1, 'monthly_rate' => 1500];

        $this->get(route('proprietor.rooms.create'))->assertOk()->assertDontSee('name="floor"', false);
        $this->post(route('proprietor.rooms.store'), $attributes)->assertSessionHasNoErrors()
            ->assertRedirect(route('proprietor.rooms.index'));

        $room = Room::where('room_number', '101')->sole();
        $room->update(['floor' => '3']);
        $this->get(route('proprietor.rooms.edit', $room))->assertOk()->assertDontSee('name="floor"', false);
        $this->put(route('proprietor.rooms.update', $room), $attributes)->assertSessionHasNoErrors()
            ->assertRedirect(route('proprietor.rooms.index'));

        $this->assertSame('3', $room->fresh()->floor);
        $this->get(route('proprietor.rooms.index', ['floor' => '999']))->assertOk()
            ->assertSee('Room 101')->assertDontSee('All floors')->assertDontSee('Floor 3');
    }
    public function test_legacy_beds_display_consistently_and_only_the_current_holder_can_select_their_bed(): void
    {
        $room = Room::create(['room_number' => 'SUPEREME 1', 'floor' => '1', 'capacity' => 2, 'monthly_rate' => 1300]);
        $heldBed = Bed::create(['room_id' => $room->id, 'bed_label' => 'Bed 1']);
        $freeBed = Bed::create(['room_id' => $room->id, 'bed_label' => 'Bed B']);
        $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_RESERVED]);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $heldBed->id,
            'start_date' => Tenant::localDate(), 'status' => 'active']);

        $this->assertSame('SUPREME 1 - Bed A', $heldBed->locationLabel());
        $this->assertSame('Bed B', $freeBed->displayLabel());
        $options = app(RoomAvailability::class);
        $this->assertSame([$freeBed->id], array_column($options->forTenant()['SUPREME']['rooms'][0]['beds'], 'id'));
        $this->assertSame([$heldBed->id, $freeBed->id], array_column($options->forTenant($tenant)['SUPREME']['rooms'][0]['beds'], 'id'));

        $this->actingAs(User::factory()->create(['role' => 'proprietor']))
            ->get(route('proprietor.rooms.index', ['type' => 'SUPREME']))->assertOk()
            ->assertViewHas('rooms', fn ($rooms) => $rooms->pluck('id')->all() === [$room->id])
            ->assertSee('SUPREME 1 - Bed A')->assertDontSee('Bed Bed B');
    }

    public function test_completed_months_use_the_recorded_room_rate(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
        $room = Room::create([
            'room_number' => 'CABIN 1', 'type' => 'CABIN', 'floor' => '1', 'capacity' => 1,
            'monthly_rate' => 1600, 'rent_history' => [['through_month' => '2026-09', 'rate' => '1500.00']],
        ]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A']);
        $tenant = Tenant::factory()->create(['move_in_date' => '2026-09-01']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id,
            'start_date' => '2026-09-01', 'status' => 'active']);

        $amounts = app(TenantBalanceService::class)->billingEntries($tenant, '2026-09-01', '2026-10-31')->pluck('amount')->all();
        $this->assertEquals([1500, 1600], $amounts);
    }
}
