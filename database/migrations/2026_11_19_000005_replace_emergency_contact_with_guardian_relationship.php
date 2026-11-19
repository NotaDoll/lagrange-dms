<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The guardian is now the only contact kept for a tenant (they receive the SMS payment reminders).
 * Before the emergency contact columns are dropped, a tenant with no guardian gets the emergency
 * contact as guardian, and the emergency relationship carries over when it describes the guardian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('guardian_relationship')->nullable()->after('guardian_contact_number');
        });

        DB::table('tenants')->orderBy('id')->each(function ($row) {
            $sameName = filled($row->guardian_name) && filled($row->emergency_contact_name)
                && strcasecmp(trim($row->guardian_name), trim($row->emergency_contact_name)) === 0;
            $noGuardian = blank($row->guardian_name) && blank($row->guardian_contact_number);
            $changes = [];

            if ($noGuardian && filled($row->emergency_contact_name)) {
                $changes['guardian_name'] = $row->emergency_contact_name;
            }
            if (blank($row->guardian_contact_number) && filled($row->emergency_contact_number) && ($noGuardian || $sameName)) {
                $changes['guardian_contact_number'] = Crypt::encryptString($row->emergency_contact_number); // the model's "encrypted" cast
            }
            if (filled($row->emergency_contact_relationship) && ($noGuardian || $sameName)) {
                $changes['guardian_relationship'] = $row->emergency_contact_relationship;
            }
            if ($changes !== []) {
                DB::table('tenants')->where('id', $row->id)->update($changes);
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_number', 'emergency_contact_relationship']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
        });

        DB::table('tenants')->orderBy('id')->each(function ($row) {
            DB::table('tenants')->where('id', $row->id)->update([
                'emergency_contact_name' => $row->guardian_name,
                'emergency_contact_number' => $row->guardian_contact_number ? Crypt::decryptString($row->guardian_contact_number) : null,
                'emergency_contact_relationship' => $row->guardian_relationship,
            ]);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('guardian_relationship');
        });
    }
};
