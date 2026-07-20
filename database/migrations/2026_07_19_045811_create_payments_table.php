<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('chit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->date('payment_date');

            $table->enum('payment_mode', ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE', 'OTHER'])
                  ->default('CASH');

            $table->string('reference_number')->nullable();
            $table->string('collected_by')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};