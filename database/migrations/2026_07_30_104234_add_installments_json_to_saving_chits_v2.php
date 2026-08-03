<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saving_chits', function (Blueprint $table) {

            if (!Schema::hasColumn('saving_chits', 'installments')) {
                $table->json('installments')->nullable();
            }

            if (!Schema::hasColumn('saving_chits', 'paid_weeks_count')) {
                $table->unsignedInteger('paid_weeks_count')->default(0);
            }

            if (!Schema::hasColumn('saving_chits', 'total_paid_amount')) {
                $table->decimal('total_paid_amount', 12, 2)->default(0);
            }

            // Indexes
            // $table->index('customer_id', 'idx_saving_chits_customer_id');
            // $table->index('status', 'idx_saving_chits_status');
        });
    }

    public function down(): void
    {
        Schema::table('saving_chits', function (Blueprint $table) {

            if (Schema::hasColumn('saving_chits', 'installments')) {
                $table->dropColumn('installments');
            }

            if (Schema::hasColumn('saving_chits', 'paid_weeks_count')) {
                $table->dropColumn('paid_weeks_count');
            }

            if (Schema::hasColumn('saving_chits', 'total_paid_amount')) {
                $table->dropColumn('total_paid_amount');
            }

            $table->dropIndex('idx_saving_chits_customer_id');
            $table->dropIndex('idx_saving_chits_status');
        });
    }
};