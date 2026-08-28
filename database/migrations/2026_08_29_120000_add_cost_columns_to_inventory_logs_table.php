<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            // Cost of stock already on hand just BEFORE this restock happened
            // (total_stock * unit_cost at that moment). Null when the
            // ingredient had no unit_cost set.
            $table->decimal('cost_old', 12, 2)->nullable()->after('quantity_change');

            // Cost of the newly received batch (its total_cost). Null when no
            // cost was entered on the restock form.
            $table->decimal('cost_new', 12, 2)->nullable()->after('cost_old');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn(['cost_old', 'cost_new']);
        });
    }
};
