<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These indexes are needed once saving_installments grows into
     * lakhs of rows (e.g. 100 customers x 100 chits x 52 weeks = 520000
     * rows). Without them, every weekly-collection lookup and pay
     * action does a full table scan.
     */
    public function up(): void
    {
        Schema::table('saving_chits', function (Blueprint $table) {
            $table->index('customer_id', 'idx_saving_chits_customer_id');
            $table->index('saving_scheme_id', 'idx_saving_chits_scheme_id');
            $table->index('status', 'idx_saving_chits_status');
        });

        Schema::table('saving_installments', function (Blueprint $table) {
            // used by: SavingChit::installments() lookups, pay(), index()
            $table->index(
                ['saving_chit_id', 'installment_number'],
                'idx_saving_installments_chit_number'
            );

            // used by: weeklyCollectionSummary(), payWeeklyCollection()
            // (filtering "week N across all chits, not yet PAID")
            $table->index(
                ['installment_number', 'status'],
                'idx_saving_installments_number_status'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saving_chits', function (Blueprint $table) {
            $table->dropIndex('idx_saving_chits_customer_id');
            $table->dropIndex('idx_saving_chits_scheme_id');
            $table->dropIndex('idx_saving_chits_status');
        });

        Schema::table('saving_installments', function (Blueprint $table) {
            $table->dropIndex('idx_saving_installments_chit_number');
            $table->dropIndex('idx_saving_installments_number_status');
        });
    }
};