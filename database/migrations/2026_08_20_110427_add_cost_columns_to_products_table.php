<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Packaging cost (cups, lids, sleeves) — added on top of recipe
            // ingredient cost when computing product_cost.
            $table->decimal('packaging_cost', 8, 2)->default(0)->after('price');

            // Only relevant for tracking_type = 'finished_stock', where there's
            // no recipe to derive cost from. Nullable — unset means "not
            // tracked yet", not free.
            $table->decimal('cost_price', 8, 2)->nullable()->after('packaging_cost');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['packaging_cost', 'cost_price']);
        });
    }
};