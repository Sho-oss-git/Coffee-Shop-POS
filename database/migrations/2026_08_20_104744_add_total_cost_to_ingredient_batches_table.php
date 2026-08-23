<?php
// database/migrations/xxxx_xx_xx_add_total_cost_to_ingredient_batches_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredient_batches', function (Blueprint $table) {
            // Whole pesos, nullable — same convention as ingredients.unit_cost.
            // Cost of this specific batch as received (e.g. invoice total),
            // independent of ingredients.unit_cost which is a manually-set
            // running cost-per-unit.
            $table->unsignedInteger('total_cost')->nullable()->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_batches', function (Blueprint $table) {
            $table->dropColumn('total_cost');
        });
    }
};