<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('complaint_ids');
            $table->unsignedInteger('complaint_count');
            $table->json('categories_included')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->longText('summary');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_summaries');
    }
};
