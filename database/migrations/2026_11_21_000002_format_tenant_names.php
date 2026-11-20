<?php

use App\Support\PersonName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Rewrites existing tenant, tenant-login and guardian names formally ("BARBIE ANGEL JOY ROYO" → "Barbie Angel Joy Royo"). */
return new class extends Migration
{
    private const NAME_COLUMNS = ['name', 'first_name', 'middle_name', 'last_name'];

    public function up(): void
    {
        $this->formatRows('tenants', [...self::NAME_COLUMNS, 'guardian_name']);
        $this->formatRows('users', self::NAME_COLUMNS, fn ($query) => $query->where('role', 'tenant'));
    }

    public function down(): void
    {
        // The original capitalization is not kept; formatted names stay formatted.
    }

    private function formatRows(string $table, array $columns, ?Closure $scope = null): void
    {
        $query = DB::table($table)->select(['id', ...$columns])->orderBy('id');
        if ($scope) {
            $scope($query);
        }

        $query->each(function ($row) use ($table, $columns) {
            $changes = [];
            foreach ($columns as $column) {
                $value = $row->{$column};
                if (is_string($value) && $value !== '' && ($formatted = PersonName::format($value)) !== $value) {
                    $changes[$column] = $formatted;
                }
            }
            if ($changes !== []) {
                DB::table($table)->where('id', $row->id)->update($changes);
            }
        });
    }
};
