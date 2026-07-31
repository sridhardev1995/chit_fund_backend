<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {

            $table->enum('status', [
                'PENDING',
                'PARTIAL',
                'PAID'
            ])
            ->default('PENDING')
            ->change();

        });
    }


    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {

            $table->string('status')
                ->default('PENDING')
                ->change();

        });
    }
};