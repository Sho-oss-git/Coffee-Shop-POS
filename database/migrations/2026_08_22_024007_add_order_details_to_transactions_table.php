<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Nullable at the DB level so existing rows aren't broken by
            // this migration — the app layer (TransactionController)
            // enforces these as required for every NEW transaction.
            $table->unsignedInteger('order_number')->nullable()->after('user_id');
            $table->string('order_type', 20)->nullable()->after('order_number'); // 'dine_in' | 'take_out'
            $table->string('customer_name')->nullable()->after('order_type');
            $table->text('notes')->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['order_number', 'order_type', 'customer_name', 'notes']);
        });
    }
};