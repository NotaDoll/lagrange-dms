<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->json('filter_params')->nullable(); // category/date range/keywords used
            $table->text('prompt_used');
            $table->text('summary_result');
            $table->decimal('response_time_seconds', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_summaries');
    }
};
