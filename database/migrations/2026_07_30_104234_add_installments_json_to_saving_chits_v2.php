<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saving_chits', function (Blueprint $table) {
            if (!Schema::hasColumn('saving_chits', 'installments')) {
                $table->json('installments')->nullable()->after('status');
            }

            if (!Schema::hasColumn('saving_chits', 'paid_weeks_count')) {
                $table->unsignedInteger('paid_weeks_count')->default(0)->after('installments');
            }

            if (!Schema::hasColumn('saving_chits', 'total_paid_amount')) {
                $table->decimal('total_paid_amount', 12, 2)->default(0)->after('paid_weeks_count');
            }
        });

        Schema::table('saving_chits', function (Blueprint $table) {
            if (!$this->indexExists('saving_chits', 'idx_saving_chits_customer_id')) {
                $table->index('customer_id', 'idx_saving_chits_customer_id');
            }

            if (!$this->indexExists('saving_chits', 'idx_saving_chits_status')) {
                $table->index('status', 'idx_saving_chits_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saving_chits', function (Blueprint $table) {
            if ($this->indexExists('saving_chits', 'idx_saving_chits_customer_id')) {
                $table->dropIndex('idx_saving_chits_customer_id');
            }

            if ($this->indexExists('saving_chits', 'idx_saving_chits_status')) {
                $table->dropIndex('idx_saving_chits_status');
            }

            $columns  = ['installments', 'paid_weeks_count', 'total_paid_amount'];
            $existing = array_values(array_filter(
                $columns,
                fn ($col) => Schema::hasColumn('saving_chits', $col)
            ));

            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );

        return count($result) > 0;
    }
};