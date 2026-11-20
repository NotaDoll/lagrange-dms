<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional key for notifications that must be sent only once, e.g. "rent:2026-12-01:3-days".
 * The unique index makes a second send for the same user and key impossible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('reference', 100)->nullable()->after('type');
            $table->unique(['user_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'reference']);
            $table->dropColumn('reference');
        });
    }
};
