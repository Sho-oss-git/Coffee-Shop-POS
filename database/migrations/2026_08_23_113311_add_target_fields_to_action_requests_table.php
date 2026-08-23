<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('action_requests', function (Blueprint $table) {
            $table->string('target_type', 30)->nullable()->after('type');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->json('payload')->nullable()->after('target_id');
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('action_requests', function (Blueprint $table) {
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropColumn(['target_type', 'target_id', 'payload']);
        });
    }
};
