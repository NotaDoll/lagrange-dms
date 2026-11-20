<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The AI's one-time urgency check of each complaint: "urgent" or "normal", and why. Null = not checked yet. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('priority', 20)->nullable()->after('status');
            $table->string('priority_reason')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['priority', 'priority_reason']);
        });
    }
};
