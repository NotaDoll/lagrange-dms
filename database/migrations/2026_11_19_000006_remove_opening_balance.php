<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Opening balances are no longer tracked: drop the tenant column and any balance-forward ledger rows. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')->where('charge_type', 'opening_balance')->delete();

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('opening_balance', 10, 2)->default(0);
        });
    }
};
