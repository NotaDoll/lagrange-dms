<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'maintenance'])
                ->default('available')
                ->change();
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('beds')->where('status', 'maintenance')->update(['status' => 'available']);

        Schema::table('beds', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied'])
                ->default('available')
                ->change();
        });
    }
};
