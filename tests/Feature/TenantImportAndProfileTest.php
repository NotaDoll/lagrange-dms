<?php

namespace Tests\Feature;

use App\Exports\TenantImportTemplateExport;
use App\Imports\TenantsImport;
use App\Models\Bed;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class TenantImportAndProfileTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $bed, string $status, ?string $date, string $email = 'shared@example.com', string $type = 'CABIN', string $number = '2'): array
    {
        return ['Ana', '', 'Dela Cruz', $email, '9171234567', '12 Mabini St, Cebu City', $type, $number, $bed,
            $status, $date, '₱5,000.00', '', 'Maria Dela Cruz', '09179876543', 'Mother', '', ''];
    }

    private function csv(array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('tenants.csv', $contents);
    }

    private function roomWithBeds(array $labels, string $number = 'CABIN 2'): Room
    {
        $room = Room::create(['room_number' => $number, 'floor' => '3', 'capacity' => count($labels), 'monthly_rate' => 5000]);
        foreach ($labels as $label) {
            Bed::create(['room_id' => $room->id, 'bed_label' => $label, 'status' => 'available']);
        }

        return $room;
    }

    public function test_preview_is_read_only_and_confirmation_isolates_rows_and_rechecks_beds(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
        $admin = User::factory()->create(['role' => 'proprietor']);
        $room = $this->roomWithBeds(['A', 'B', 'C', 'D', 'E']);
        $file = $this->csv([
            array_map('strtoupper', TenantsImport::COLUMNS),
            array_merge($this->row('a', 'ACTIVE', '2022-11-13'), ['₱1,250.25']),
            $this->row('B', 'reserved', null),
            $this->row('A', 'reserved', null),
            $this->row('C', 'reserved', '2026-11-13'),
            $this->row('D', 'active', '2026-11-14'),
            $this->row('E', 'reserved', null, 'late-bed@example.com'),
        ]);

        $response = $this->actingAs($admin)->post(route('proprietor.tenants.import.store'), ['file' => $file, 'consent' => '1']);
        $response->assertSessionHasNoErrors()->assertRedirect();
        $token = basename($response->headers->get('Location'));
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 1);

        $this->get(route('proprietor.tenants.import.preview', $token))->assertOk()
            ->assertViewHas('valid', 3)->assertViewHas('total', 6)
            ->assertSee('Bed already used in row 2')
            ->assertSee('Leave the move-in date blank for a Reserved tenant.')
            ->assertDontSee('12 Mabini St, Cebu City');
        $report = $this->get(route('proprietor.tenants.import.errors', $token))->assertDownload('tenant-import-errors.csv');
        $this->assertStringContainsString('row,column,reason', $report->streamedContent());
        $this->assertStringNotContainsString('DELACRUZ09171234567', $report->streamedContent());

        $occupied = Tenant::factory()->create(['status' => Tenant::STATUS_RESERVED]);
        RoomAssignment::create(['tenant_id' => $occupied->id, 'bed_id' => $room->beds()->where('bed_label', 'E')->sole()->id,
            'start_date' => Tenant::localDate(), 'status' => 'active']);

        $this->post(route('proprietor.tenants.import.confirm', $token))->assertRedirect(route('proprietor.tenants.import.results'));
        $this->get(route('proprietor.tenants.import.results'))->assertOk()
            ->assertViewHas('result', fn ($result) => $result['imported'] === 2 && $result['logins_created'] === 1
                && count($result['without_login']) === 1 && count($result['errors']) >= 4)
            ->assertDontSee('DELACRUZ09171234567');
        $this->assertDatabaseCount('tenants', 3);
        $longStay = Tenant::where('email', 'shared@example.com')->where('status', Tenant::STATUS_ACTIVE)->sole();
        $this->assertSame('2022-11-13', $longStay->move_in_date->toDateString());
        $this->assertSame('1250.25', $longStay->opening_balance);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseMissing('users', ['email' => 'late-bed@example.com']);

        $active = Tenant::where('status', Tenant::STATUS_ACTIVE)->sole();
        $this->assertSame('09171234567', $active->contact_number);
        $this->assertSame('12 Mabini St, Cebu City', $active->home_address);
        $this->assertSame('5000.00', $active->security_deposit);
        $this->assertSame('0.00', $active->advance_payment);
        $this->assertSame($admin->id, $active->consent_recorded_by);
        $this->assertNotNull($active->data_privacy_consent_at);
        $this->assertTrue($active->user->must_change_password);
        $this->assertTrue(Hash::check('DELACRUZ09171234567', $active->user->password));
        $reserved = Tenant::where('status', Tenant::STATUS_RESERVED)->where('email', 'shared@example.com')->sole();
        $this->assertNull($reserved->user_id);
        $this->assertNull($reserved->move_in_date);
        $this->assertSame($admin->id, $reserved->consent_recorded_by);
        $this->assertDatabaseCount('payments', 0);

        $this->post(route('proprietor.tenants.import.confirm', $token))->assertRedirect(route('proprietor.tenants.import.create'));
        $this->assertDatabaseCount('tenants', 3);
    }

    public function test_import_limits_legacy_headers_consent_and_cancel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $this->roomWithBeds(['A']);
        $route = route('proprietor.tenants.import.store');
        $legacy = [['name', 'email'], ['Example Tenant', 'old@example.com']];

        $this->post($route, ['file' => $this->csv($legacy)])->assertSessionHasErrors('consent');
        $this->post($route, ['file' => $this->csv($legacy), 'consent' => '1'])->assertSessionHasErrors([
            'file' => 'This looks like the old template. Please download the new template.',
        ]);
        $oldSpreadsheet = new class($legacy) implements FromArray {
            public function __construct(private readonly array $rows) {}
            public function array(): array { return $this->rows; }
        };
        $oldExcel = UploadedFile::fake()->createWithContent('legacy.xlsx', Excel::raw($oldSpreadsheet, ExcelFormat::XLSX));
        $this->post($route, ['file' => $oldExcel, 'consent' => '1'])->assertSessionHasErrors([
            'file' => 'This looks like the old template. Please download the new template.',
        ]);

        $this->post($route, ['file' => UploadedFile::fake()->create('large.csv', 2049, 'text/plain'), 'consent' => '1'])
            ->assertSessionHasErrors('file');
        $rows = [TenantsImport::COLUMNS, ...array_fill(0, 201, $this->row('A', 'reserved', null))];
        $this->post($route, ['file' => $this->csv($rows), 'consent' => '1'])->assertSessionHasErrors('file');

        $response = $this->post($route, ['file' => $this->csv([TenantsImport::COLUMNS, [], $this->row('A', 'reserved', null)]), 'consent' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $token = basename($response->headers->get('Location'));
        $this->get(route('proprietor.tenants.import.preview', $token))->assertOk()->assertViewHas('total', 1);
        $this->delete(route('proprietor.tenants.import.cancel', $token))->assertRedirect(route('proprietor.tenants.import.create'));
        $this->get(route('proprietor.tenants.import.preview', $token))->assertRedirect(route('proprietor.tenants.import.create'));
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_preview_matches_legacy_rooms_and_reports_type_room_and_bed_errors(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $room = $this->roomWithBeds(['Bed 1', 'Bed 2'], 'SUPEREME 1');
        $this->roomWithBeds(['A'], 'CABIN');
        $held = Tenant::factory()->create(['status' => Tenant::STATUS_RESERVED]);
        RoomAssignment::create([
            'tenant_id' => $held->id,
            'bed_id' => $room->beds()->where('bed_label', 'Bed 1')->sole()->id,
            'start_date' => Tenant::localDate(), 'status' => 'active',
        ]);
        $file = $this->csv([
            TenantsImport::COLUMNS,
            $this->row('A', 'reserved', null, type: 'supreme', number: '1'),
            $this->row('B', 'reserved', null, type: 'supreme', number: '1'),
            $this->row('A', 'reserved', null, type: 'unknown', number: '1'),
            $this->row('A', 'reserved', null, type: 'CABIN', number: '3'),
            $this->row('Z', 'reserved', null, type: 'CABIN', number: '1'),
            $this->row('A', 'reserved', null, type: 'CABIN', number: '1'),
        ]);
        $response = $this->post(route('proprietor.tenants.import.store'), ['file' => $file, 'consent' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $token = basename($response->headers->get('Location'));

        $this->get(route('proprietor.tenants.import.preview', $token))->assertOk()->assertViewHas('valid', 2)
            ->assertSee('Unknown room type')->assertSee('Room 3 does not exist in CABIN')
            ->assertSee('Bed Z does not exist')->assertSee('Bed is occupied or reserved');
    }

    public function test_download_template_has_bom_headers_and_two_examples(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $download = $this->get(route('proprietor.tenants.import.template'))->assertDownload('tenant-import-template.csv');
        $contents = file_get_contents($download->baseResponse->getFile()->getPathname());

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contents);
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), preg_split('/\r?\n/', trim(substr($contents, 3))));
        $this->assertSame(TenantsImport::COLUMNS, $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertSame('active', $rows[1][9]);
        $this->assertSame('09171234567', $rows[1][4]);
        $this->assertSame('reserved', $rows[2][9]);
        $this->assertSame('', $rows[2][10]);
    }

    public function test_excel_preview_reads_the_new_columns_without_saving(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
        $this->actingAs(User::factory()->create(['role' => 'proprietor']));
        $this->roomWithBeds(['A']);
        $this->roomWithBeds(['B'], 'SUITE 1');
        $file = UploadedFile::fake()->createWithContent('tenants.xlsx', Excel::raw(new TenantImportTemplateExport(), ExcelFormat::XLSX));

        $response = $this->post(route('proprietor.tenants.import.store'), ['file' => $file, 'consent' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $token = basename($response->headers->get('Location'));
        $this->get(route('proprietor.tenants.import.preview', $token))->assertOk()
            ->assertViewHas('valid', 2)->assertViewHas('total', 2);
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_my_information_only_shows_the_signed_in_tenant_and_honors_password_gate(): void
    {
        $own = User::factory()->create(['role' => 'tenant']);
        $other = User::factory()->create(['role' => 'tenant']);
        $otherTenant = Tenant::factory()->create(['user_id' => $other->id, 'home_address' => 'Private other address']);
        Tenant::factory()->create([
            'user_id' => $own->id, 'home_address' => 'My own address', 'status' => Tenant::STATUS_RESERVED,
            'security_deposit' => 5000, 'advance_payment' => 100, 'guardian_name' => 'My guardian',
            'data_privacy_consent_at' => now(), 'consent_recorded_by' => $other->id,
        ]);
        $route = route('tenant.profile.show');

        $this->actingAs($own)->get($route.'?tenant_id='.$otherTenant->id)->assertOk()
            ->assertSee('My own address')->assertSee('My guardian')->assertSee('Not yet moved in')
            ->assertSee('5,000.00')->assertSee('To correct any information, please contact the dormitory admin.')
            ->assertDontSee('Private other address')->assertDontSee('Recorded by')->assertDontSee($own->password);
        $this->get($route.'/'.$otherTenant->id)->assertNotFound();
        $this->post($route)->assertStatus(405);
        $own->update(['must_change_password' => true]);
        $this->actingAs($own)->get($route)->assertRedirect(route('tenant.password.edit'));
        $this->get(route('tenant.password.edit'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'tenant']))->get($route)->assertOk()
            ->assertSee('find a tenant record linked to your account');
    }
}
