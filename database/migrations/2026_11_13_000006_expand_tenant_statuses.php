<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });

        DB::table('tenants')->update(['status' => DB::raw(
            "CASE
                WHEN LOWER(TRIM(status)) IN ('reserved', 'reservation', 'pending', 'booked') THEN 'reserved'
                WHEN LOWER(TRIM(status)) IN ('inactive', 'disabled', 'suspended') THEN 'inactive'
                WHEN LOWER(TRIM(status)) IN ('moved_out', 'moved out', 'moved-out', 'checked_out', 'departed') THEN 'moved_out'
                ELSE 'active'
            END"
        )]);
    }

    public function down(): void
    {
        DB::table('tenants')->where('status', 'moved_out')->update(['status' => 'inactive']);
        DB::table('tenants')->whereNotIn('status', ['active', 'inactive'])->update(['status' => 'active']);

        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])->default('active')->change();
        });
    }
};
