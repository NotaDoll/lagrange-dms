<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** "Moved Out" is now part of "Inactive", so tenants use reserved, active and inactive only. */
    public function up(): void
    {
        DB::table('tenants')->where('status', 'moved_out')->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        // Merged rows cannot be told apart from tenants that were already inactive.
    }
};
