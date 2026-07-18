<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {

            $table->id();

            $table->string('customer_code')->unique();

            $table->string('name');

            $table->string('phone_number',15)->unique();

            $table->text('address');

            $table->string('aadhaar_number',12)->unique();

            $table->string('pan_number',10)->nullable();

            $table->string('bank_name')->nullable();

            $table->string('account_number')->nullable();

            $table->string('ifsc')->nullable();

            $table->string('upi_id')->nullable();

            $table->string('reference_name')->nullable();

            $table->string('reference_number',15)->nullable();

            $table->string('nominee_name')->nullable();

            $table->string('nominee_number',15)->nullable();

            $table->string('photo')->nullable();

            $table->text('remarks')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};