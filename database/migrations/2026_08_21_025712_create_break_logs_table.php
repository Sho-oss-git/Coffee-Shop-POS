<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('break_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('break_started_at');
            $table->timestamp('break_ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'break_started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_logs');
    }
};