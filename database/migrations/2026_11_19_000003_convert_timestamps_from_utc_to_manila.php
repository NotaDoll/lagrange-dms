<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The app clock moved from UTC to Asia/Manila (config/app.php). Date-times saved before that were
 * written as UTC, so shift them forward 8 hours (the Philippines has no daylight saving) so that
 * old and new records read the same way. Date-only columns (due dates, move-in dates) are untouched.
 */
return new class extends Migration
{
    private const HOURS = 8;

    public function up(): void
    {
        $this->shift('+');
    }

    public function down(): void
    {
        $this->shift('-');
    }

    private function shift(string $direction): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // Fresh databases (tests, new installs) have nothing written in UTC.
        }

        $columns = DB::select(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('timestamp', 'datetime') AND TABLE_NAME <> 'migrations'"
        );

        foreach ($columns as $column) {
            DB::statement(sprintf(
                'UPDATE `%s` SET `%s` = `%2$s` %s INTERVAL %d HOUR WHERE `%2$s` IS NOT NULL',
                $column->t, $column->c, $direction, self::HOURS
            ));
        }
    }
};
