<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaint_summaries', function (Blueprint $table) {
            $table->json('theme_complaint_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('complaint_summaries', function (Blueprint $table) {
            $table->dropColumn('theme_complaint_ids');
        });
    }
};
