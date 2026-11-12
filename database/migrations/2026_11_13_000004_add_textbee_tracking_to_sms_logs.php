<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->enum('status', ['sent', 'failed', 'queued', 'unknown', 'delivered'])->default('sent')->change();
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('textbee_batch_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        DB::table('sms_logs')->where('status', 'delivered')->update(['status' => 'sent']);
        DB::table('sms_logs')->whereIn('status', ['queued', 'unknown'])->update(['status' => 'failed']);

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropUnique(['textbee_batch_id']);
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn('textbee_batch_id');
            $table->enum('status', ['sent', 'failed'])->default('sent')->change();
        });
    }
};
