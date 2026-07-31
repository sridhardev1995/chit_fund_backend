<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saving_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('scheme_code')->unique();       // SP00001
            $table->string('name');                          // e.g. "100 Weekly Plan"
            $table->decimal('weekly_amount', 10, 2);          // e.g. 100.00
            $table->unsignedInteger('total_weeks');           // e.g. 52
            $table->decimal('maturity_amount', 12, 2);        // e.g. 6000.00 (final payout)
            $table->boolean('status')->default(true);         // active / inactive plan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saving_schemes');
    }
};