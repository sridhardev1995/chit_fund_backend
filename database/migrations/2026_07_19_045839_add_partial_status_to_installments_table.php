<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
   public function up(): void
{
    DB::statement("
        CREATE TYPE installment_status AS ENUM (
            'PENDING',
            'PARTIAL',
            'PAID'
        )
    ");

    // Remove old default first
    DB::statement("
        ALTER TABLE installments
        ALTER COLUMN status DROP DEFAULT
    ");

    // Change column type
    DB::statement("
        ALTER TABLE installments
        ALTER COLUMN status TYPE installment_status
        USING status::text::installment_status
    ");

    // Add default again
    DB::statement("
        ALTER TABLE installments
        ALTER COLUMN status SET DEFAULT 'PENDING'
    ");
}


public function down(): void
{
    DB::statement("
        ALTER TABLE installments
        ALTER COLUMN status TYPE VARCHAR(50)
        USING status::text
    ");

    DB::statement("
        DROP TYPE installment_status
    ");
}
};