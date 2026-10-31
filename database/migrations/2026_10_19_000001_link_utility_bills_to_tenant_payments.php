<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utility_bills', function (Blueprint $table) {
            $table->string('other_description', 100)->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('utility_bill_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['utility_bill_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['utility_bill_id']);
            $table->dropUnique(['utility_bill_id', 'tenant_id']);
            $table->dropColumn('utility_bill_id');
        });
        Schema::table('utility_bills', fn (Blueprint $table) => $table->dropColumn('other_description'));
    }
};
