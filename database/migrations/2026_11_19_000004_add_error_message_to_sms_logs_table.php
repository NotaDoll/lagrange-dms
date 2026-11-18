<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            // Why a text failed (e.g. "TextBee rejected access. Check the API key..."), so it can be fixed.
            $table->string('error_message', 255)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });
    }
};
