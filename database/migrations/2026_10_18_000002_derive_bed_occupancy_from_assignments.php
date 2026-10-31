<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Active assignments now determine occupancy; bed.status stores only its base state.
        DB::table('beds')->where('status', 'occupied')->update(['status' => 'available']);

        Schema::table('beds', function (Blueprint $table) {
            $table->enum('status', ['available', 'maintenance'])
                ->default('available')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'maintenance'])
                ->default('available')
                ->change();
        });

        DB::table('beds')
            ->whereIn('id', DB::table('room_assignments')->where('status', 'active')->select('bed_id'))
            ->update(['status' => 'occupied']);
    }
};
