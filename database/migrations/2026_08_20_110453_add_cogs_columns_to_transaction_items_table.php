<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            // Snapshot of the product's per-unit cost AT THE MOMENT OF SALE.
            // Never recompute historical COGS from today's recipe/ingredient
            // costs — prices and recipes drift over time and past reports
            // must stay stable. Nullable: if an ingredient was missing a
            // unit_cost at sale time, the sale still completes, but this
            // line can't claim a COGS figure.
            $table->decimal('unit_cost', 10, 4)->nullable()->after('subtotal');
            $table->decimal('cogs', 10, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'cogs']);
        });
    }
};