<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A batch is received in whatever unit the delivery came in. Today the code
     * implicitly assumes every batch is already in the ingredient's display
     * unit — this makes that explicit so the consumption service can convert
     * per-batch instead of guessing.
     *
     * Also widens quantity/remaining_quantity precision from decimal:2 to
     * decimal:4 so unit-conversion math (e.g. 10 g / 1000 = 0.01 kg, chained
     * across many sales) doesn't lose precision internally. Rounding for
     * display only happens in the frontend / UnitConversionService::formatForDisplay().
     *
     * Made idempotent (Schema::hasColumn checks) so re-running migrations —
     * e.g. against a database that already has these columns from an earlier
     * partial run or an imported schema — does not throw "duplicate column".
     */
    public function up(): void
    {
        Schema::table('ingredient_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('ingredient_batches', 'unit')) {
                $table->string('unit', 5)->nullable()->after('ingredient_id');
            }
        });

        if (Schema::hasColumn('ingredient_batches', 'unit')) {
            // Backfill every existing batch with its parent ingredient's current unit.
            DB::statement(
                'UPDATE ingredient_batches ib
                 JOIN ingredients i ON i.id = ib.ingredient_id
                 SET ib.unit = i.unit
                 WHERE ib.unit IS NULL'
            );
        }

        // Requires doctrine/dbal for column::change(). If that package isn't
        // installed, comment this block out — decimal:2 will still work
        // correctly for the values in this spec, just with less headroom.
        Schema::table('ingredient_batches', function (Blueprint $table) {
            if (Schema::hasColumn('ingredient_batches', 'unit')) {
                $table->string('unit', 5)->nullable(false)->change();
            }
            $table->decimal('quantity', 14, 4)->change();
            $table->decimal('remaining_quantity', 14, 4)->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('ingredient_batches', 'unit')) {
            Schema::table('ingredient_batches', function (Blueprint $table) {
                $table->dropColumn('unit');
                $table->decimal('quantity', 8, 2)->change();
                $table->decimal('remaining_quantity', 8, 2)->change();
            });
        }
    }
};
