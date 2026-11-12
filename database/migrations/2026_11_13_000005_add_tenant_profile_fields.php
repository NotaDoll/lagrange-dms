<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('home_address')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->decimal('advance_payment', 10, 2)->default(0);
            $table->timestamp('data_privacy_consent_at')->nullable();
            $table->foreignId('consent_recorded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consent_recorded_by');
            $table->dropColumn([
                'home_address', 'emergency_contact_relationship', 'security_deposit', 'advance_payment',
                'data_privacy_consent_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
