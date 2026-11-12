<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('contact_number')->nullable();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        DB::table('tenants')->whereNotNull('user_id')->update([
            'email' => DB::raw('(SELECT LOWER(TRIM(users.email)) FROM users WHERE users.id = tenants.user_id)'),
        ]);
    }

    public function down(): void
    {
        if (DB::table('tenants')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Rollback requires every tenant to have its own linked login. Accountless tenant records were preserved.');
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->dropColumn(['email', 'name', 'first_name', 'middle_name', 'last_name', 'contact_number']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
