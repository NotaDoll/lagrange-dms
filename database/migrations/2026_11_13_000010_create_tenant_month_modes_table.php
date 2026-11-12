<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_month_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->string('mode', 16);
            $table->decimal('amount', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_month_modes');
    }
};
