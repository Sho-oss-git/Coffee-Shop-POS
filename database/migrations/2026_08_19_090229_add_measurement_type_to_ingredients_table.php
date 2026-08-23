<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMPORTANT: this migration deliberately keeps the existing `unit` column
     * on ingredients rather than renaming it. `unit` continues to be the
     * INVENTORY DISPLAY unit (kg / L / pcs) — nothing about existing reads
     * (Ingredient.vue, Product.vue, IngredientController, etc.) breaks.
     *
     * We only add `measurement_type`, which classifies that display unit into
     * one of the three conversion families (weight / volume / piece) so
     * UnitConversionService knows how to convert it.
     */
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('measurement_type', 10)->default('weight')->after('name');
        });

        // Backfill from the existing unit values.
        DB::table('ingredients')->whereIn('unit', ['g', 'kg'])->update(['measurement_type' => 'weight']);
        DB::table('ingredients')->whereIn('unit', ['ml', 'l'])->update(['measurement_type' => 'volume']);
        DB::table('ingredients')->where('unit', 'pcs')->update(['measurement_type' => 'piece']);
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('measurement_type');
        });
    }
};