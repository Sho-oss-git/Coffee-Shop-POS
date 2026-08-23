<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    protected $fillable = [
        'ingredient_id', 'ingredient_batch_id', 'product_id',
        'type', 'quantity_change', 'note',
    ];

    protected function casts(): array
    {
        return ['quantity_change' => 'decimal:2'];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function ingredientBatch(): BelongsTo
    {
        return $this->belongsTo(IngredientBatch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}