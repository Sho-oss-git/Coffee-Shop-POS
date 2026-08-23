<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'price',
        'image',
        'is_available',
        'tracking_type',
        'stock_quantity',
        'packaging_cost',
        'cost_price',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'stock_quantity' => 'integer',
        ];
    }

    public function recipe(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductIngredient::class);
    }

    public function ingredients(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredients')
            ->withPivot('quantity', 'unit')
            ->withTimestamps();
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    public function isAvailable(): bool
    {
        if (! $this->is_available) {
            return false;
        }

        if ($this->tracking_type === 'finished_stock') {
            return $this->stock_quantity > 0;
        }

        return $this->recipe->every(
            fn (ProductIngredient $line) => $line->ingredient && $line->ingredient->total_stock > 0
        );
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where('name', 'like', "%{$term}%");
    }
}