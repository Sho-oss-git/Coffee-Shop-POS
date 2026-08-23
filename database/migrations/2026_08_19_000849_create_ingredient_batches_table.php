<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            // Stored in the ingredient's BASE unit (g or ml or pcs) so totals never need conversion.
            $table->decimal('quantity', 10, 2);
            $table->decimal('remaining_quantity', 10, 2);
            $table->date('received_date');
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->index(['ingredient_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_batches');
    }
};