<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saving_installments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saving_chit_id')
                  ->constrained('saving_chits')
                  ->cascadeOnDelete();

            $table->unsignedInteger('installment_number');    // 1..total_weeks
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->date('paid_date')->nullable();
            $table->string('status')->default('PENDING');     // PENDING, PAID

            $table->timestamps();

            $table->unique(['saving_chit_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saving_installments');
    }
};