<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_id',
        'unit',
        'quantity',
        'remaining_quantity',
        'received_date',
        'expiry_date',
        'total_cost',
    ];

    protected $appends = ['status'];

    protected function casts(): array
    {
        return [
            // Widened from decimal:2 -> decimal:4 so internal unit-conversion math
            // (e.g. 9990 g -> 9.99 kg -> further sales) doesn't lose precision.
            // Rounding for display only happens at render time.
            'quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',
            'received_date' => 'date',
            'expiry_date' => 'date',
            // Whole pesos, nullable — cost of this specific batch as received
            // (e.g. invoice total). Independent of ingredients.unit_cost,
            // which is a manually-set running cost-per-unit.
            'total_cost' => 'integer',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function getStatusAttribute(): string
    {
        if (! $this->expiry_date) {
            return 'active';
        }

        $today = now()->startOfDay();

        if ($this->expiry_date->lt($today)) {
            return 'expired';
        }

        if ($today->diffInDays($this->expiry_date) <= config('inventory.expiry_warning_days', 5)) {
            return 'expiring_soon';
        }

        return 'active';
    }
}