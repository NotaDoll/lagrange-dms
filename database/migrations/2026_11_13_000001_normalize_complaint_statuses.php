<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('complaints')
            ->whereNotIn('status', ['in_progress', 'resolved'])
            ->orWhereNull('status')
            ->update(['status' => 'in_progress']);

        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('status', ['in_progress', 'resolved'])->default('in_progress')->change();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('status', ['submitted', 'in_progress', 'resolved'])->default('submitted')->change();
        });
    }
};
