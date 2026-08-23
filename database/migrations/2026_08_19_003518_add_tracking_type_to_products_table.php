<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'tracking_type')) {
                $table->enum('tracking_type', ['recipe', 'finished_stock'])->default('recipe')->after('is_available');
            }

            if (! Schema::hasColumn('products', 'stock_quantity')) {
                $table->unsignedInteger('stock_quantity')->nullable()->after('tracking_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['tracking_type', 'stock_quantity']);
        });
    }
};