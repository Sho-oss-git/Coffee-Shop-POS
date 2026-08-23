<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY status ENUM('completed', 'refunded', 'voided') NOT NULL DEFAULT 'completed'");
    }

    public function down(): void
    {
        DB::table('transactions')
            ->where('status', 'refunded')
            ->update(['status' => 'completed']);

        DB::statement("ALTER TABLE transactions MODIFY status ENUM('completed', 'voided') NOT NULL DEFAULT 'completed'");
    }
};
