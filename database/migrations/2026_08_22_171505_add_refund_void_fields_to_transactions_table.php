<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Refund
            $table->decimal('refund_amount', 10, 2)->nullable()->after('status');
            $table->string('refund_reason')->nullable()->after('refund_amount');
            $table->foreignId('refunded_by')->nullable()->after('refund_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable()->after('refunded_by');

            // Void
            $table->string('void_reason')->nullable()->after('refunded_at');
            $table->foreignId('voided_by')->nullable()->after('void_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropConstrainedForeignId('voided_by');

            $table->dropColumn([
                'refund_amount',
                'refund_reason',
                'refunded_at',
                'void_reason',
                'voided_at',
            ]);
        });
    }
};