<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE installments MODIFY status ENUM('PENDING','PARTIAL','PAID') DEFAULT 'PENDING'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE installments MODIFY status ENUM('PENDING','PAID') DEFAULT 'PENDING'");
    }
};