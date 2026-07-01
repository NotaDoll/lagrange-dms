<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('bed_label'); // e.g. "Bed A", "Bed 1"
            $table->enum('status', ['available', 'occupied'])->default('available');
            $table->timestamps();

            $table->unique(['room_id', 'bed_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beds');
    }
};
