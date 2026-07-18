<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('chit_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->integer('installment_number');


            $table->date('due_date');


            $table->decimal('amount',10,2);


            $table->decimal('paid_amount',10,2)
                ->default(0);


            /*
                PENDING
                PARTIAL
                PAID
                OVERDUE
            */

            $table->string('status')
                ->default('PENDING');


            $table->date('paid_date')
                ->nullable();


            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};