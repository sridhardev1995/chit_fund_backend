<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('chits', function (Blueprint $table) {

            $table->id();


            // Auto generated
            // CH00001
            $table->string('chit_code')
                  ->unique();


            // Customer
            $table->foreignId('customer_id')
                  ->constrained()
                  ->cascadeOnDelete();


            // Original chit value
            $table->decimal('chit_amount',10,2);


            // Frozen commission
            $table->decimal('commission_rate',5,2);


            $table->decimal('commission_amount',10,2);


            // Amount customer receives
            $table->decimal('disbursed_amount',10,2);


            // Weekly repayment
            $table->integer('total_weeks');


            $table->decimal('weekly_installment',10,2);


            $table->date('start_date')
                  ->nullable();


            /*
              ACTIVE
              COMPLETED
              CLOSED
            */
            $table->string('status')
                  ->default('ACTIVE');


            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chits');
    }

};