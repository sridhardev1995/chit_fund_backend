<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saving_chits', function (Blueprint $table) {
            $table->id();
            $table->string('saving_chit_code')->unique();     // SC00001

            $table->foreignId('customer_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('saving_scheme_id')
                  ->constrained('saving_schemes')
                  ->restrictOnDelete();

            // Frozen copy of scheme values at the time this chit was sold,
            // so later edits to the scheme never affect existing chits.
            $table->decimal('weekly_amount', 10, 2);
            $table->unsignedInteger('total_weeks');
            $table->decimal('total_collection', 12, 2);       // weekly_amount x total_weeks
            $table->decimal('maturity_amount', 12, 2);        // final amount customer gets

            $table->date('start_date');
            $table->string('status')->default('ACTIVE');      // ACTIVE, COMPLETED, CLOSED

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saving_chits');
    }
};