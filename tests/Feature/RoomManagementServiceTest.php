<?php

namespace Tests\Feature;

use App\Models\Room;
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
            'floor' => 1,
            'capacity' => 3,
            'monthly_rate' => 1500.00,
        ]);

        $room = Room::latest('id')->first();

        $this->assertSame(['Bed A', 'Bed B', 'Bed C'], $room->beds()->pluck('bed_label')->all());
    }
}
