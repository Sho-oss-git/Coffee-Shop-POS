<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cost per unit, in the ingredient's own display unit (same unit as
     * `unit` / total_stock — e.g. cost per kg if unit is kg). Whole pesos
     * only, no cents. Nullable and opt-in: ingredients without a cost set
     * are simply excluded from the Total Stock Value report rather than
     * being treated as ₱0.
     */
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->unsignedInteger('unit_cost')->nullable()->after('minimum_stock');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};