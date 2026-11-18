<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Imported rent follows the tenant's move-in day, whatever date the spreadsheet used
 * (the old sheet put everything on the 30th). Paid months keep their paid date, so a
 * tenant who paid on the 25th is now 11 days late against a due date of the 14th.
 */
class PaymentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_rent_moves_to_the_move_in_day_for_paid_and_unpaid_rows(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(10, 0));
        $tenant = Tenant::create(['user_id' => User::factory()->create()->id, 'status' => 'active', 'move_in_date' => '2026-05-14']);
        $room = Room::create(['room_number' => '101', 'floor' => '1', 'capacity' => 1, 'monthly_rate' => 3000]);
        $bed = Bed::create(['room_id' => $room->id, 'bed_label' => 'A', 'status' => 'available']);
        RoomAssignment::create(['tenant_id' => $tenant->id, 'bed_id' => $bed->id, 'start_date' => '2026-05-14', 'status' => 'active']);

        $csv = implode("\n", [
            'tenant_id,amount,due_date,status,paid_date',
            "{$tenant->id},3000,2026-09-30,paid,2026-09-25",
            "{$tenant->id},3000,2026-10-30,pending,",
            "{$tenant->id},3000,2026-05-30,paid,2026-05-30", // before the first rent date (Jun 14)
        ]);

        $this->actingAs(User::factory()->create(['role' => 'proprietor']))
            ->post(route('proprietor.payments.import'), ['excel_file' => UploadedFile::fake()->createWithContent('payments.csv', $csv)])
            ->assertRedirect()
            ->assertSessionHas('warning', 'Successfully imported 2 payments, but some rows failed.')
            ->assertSessionHas('import_errors', fn ($errors) => count($errors) === 1 && str_contains($errors[0], 'Row 4'));

        $september = Payment::whereMonth('due_date', 9)->sole();
        $this->assertSame(['2026-09-14', 'paid', '2026-09-25', 11], [
            $september->due_date->toDateString(), $september->status, $september->paid_date->toDateString(), $september->days_overdue,
        ]);

        $october = Payment::whereMonth('due_date', 10)->sole();
        $this->assertSame(['2026-10-14', 'overdue', 36], [$october->due_date->toDateString(), $october->current_status, $october->current_days_overdue]);
    }
}
