<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            // Was integer (whole pesos only) — changed to decimal so costs
            // like ₱0.10/ml or ₱1.50/g can be entered directly, and so
            // costPerBaseUnit() doesn't lose precision when dividing by 1000.
            $table->decimal('unit_cost', 10, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->integer('unit_cost')->nullable()->change();
        });
    }
};