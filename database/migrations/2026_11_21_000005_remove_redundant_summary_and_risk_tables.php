<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes two redundant tables:
 * - complaint_summaries duplicated ai_summaries. Its rows are copied into ai_summaries first,
 *   so the Complaints page history is kept.
 * - risk_flags was never read; payment risk is computed live by PaymentRiskService::evaluate().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('complaint_summaries')) {
            DB::table('complaint_summaries')->orderBy('id')->each(function ($row) {
                $decode = fn ($value) => $value === null ? null : json_decode($value, true);
                $themes = $decode($row->theme_complaint_ids ?? null);
                $result = is_array($themes) && $themes !== []
                    ? (array_is_list($themes) ? ['themes' => $themes] : $themes)
                    : null;

                DB::table('ai_summaries')->insert([
                    'generated_by' => $row->user_id,
                    'filter_params' => json_encode([
                        'complaint_ids' => $decode($row->complaint_ids) ?? [],
                        'complaint_count' => (int) $row->complaint_count,
                        'categories' => $decode($row->categories_included) ?? [],
                        'period_start' => $row->period_start,
                        'period_end' => $row->period_end,
                    ]),
                    'prompt_used' => '',
                    'summary_result' => $result === null ? $row->summary : json_encode($result, JSON_UNESCAPED_UNICODE),
                    'response_time_seconds' => null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            });

            Schema::drop('complaint_summaries');
        }

        Schema::dropIfExists('risk_flags');
    }

    public function down(): void
    {
        if (! Schema::hasTable('risk_flags')) {
            Schema::create('risk_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->enum('risk_level', ['low', 'medium', 'high'])->default('low');
                $table->unsignedTinyInteger('late_payments_6mo')->default(0);
                $table->unsignedTinyInteger('consecutive_late_months')->default(0);
                $table->decimal('avg_days_overdue', 5, 1)->default(0);
                $table->unsignedInteger('days_since_last_default')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('complaint_summaries')) {
            Schema::create('complaint_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->json('complaint_ids');
                $table->unsignedInteger('complaint_count');
                $table->json('categories_included')->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->longText('summary');
                $table->json('theme_complaint_ids')->nullable();
                $table->timestamps();
            });
        }
    }
};
