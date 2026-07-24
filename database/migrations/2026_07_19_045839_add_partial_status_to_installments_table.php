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

    DB::statement("
        ALTER TABLE installments
        ALTER COLUMN status TYPE installment_status
        USING status::text::installment_status
    ");
}
};