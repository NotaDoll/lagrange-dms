<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low');
            // Matches the four IFTTT indicators in Table 3-6
            $table->unsignedTinyInteger('late_payments_6mo')->default(0);
            $table->unsignedTinyInteger('consecutive_late_months')->default(0);
            $table->decimal('avg_days_overdue', 5, 1)->default(0);
            $table->unsignedInteger('days_since_last_default')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_flags');
    }
};
