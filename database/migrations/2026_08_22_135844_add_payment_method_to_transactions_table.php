<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Existing rows (all cash, from before this feature) default
            // cleanly to 'cash' rather than needing a backfill.
            $table->string('payment_method', 20)->default('cash')->after('notes');
            $table->string('gcash_reference_number', 50)->nullable()->after('payment_method');
            $table->string('gcash_proof')->nullable()->after('gcash_reference_number'); // storage path, mirrors Product::image
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'gcash_reference_number', 'gcash_proof']);
        });
    }
};