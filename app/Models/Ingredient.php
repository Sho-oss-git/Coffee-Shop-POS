<?php

namespace App\Models;

use App\Services\UnitConversionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory;

    /**
     * NOTE: `unit` remains the INVENTORY DISPLAY unit (kg / L / pcs) — unchanged
     * from before. `measurement_type` classifies it as weight/volume/piece so
     * UnitConversionService knows which family (and which recipe units) apply.
     * `unit_cost` is the cost per display unit (e.g. cost per kg), in whole
     * pesos — nullable and opt-in, set manually per ingredient rather than
     * derived from batches.
     */
    protected $fillable = ['name', 'measurement_type', 'unit', 'minimum_stock', 'unit_cost'];

    protected $appends = ['total_stock', 'status', 'nearest_expiry', 'allowed_recipe_units', 'total_value'];

    protected function casts(): array
{
    return [
        'minimum_stock' => 'decimal:4',
        // Was 'integer' — blocked fractional costs like ₱0.10/ml or
        // ₱1.50/g. decimal:4 lets small per-unit costs be entered and
        // stored precisely.
        'unit_cost' => 'decimal:4',
    ];
}

    public function batches(): HasMany
    {
        return $this->hasMany(IngredientBatch::class);
    }

    /** Batches that still have stock and are not expired. */
    public function validBatches(): HasMany
    {
        return $this->batches()
            ->where('remaining_quantity', '>', 0)
            ->where(function (Builder $query) {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now()->toDateString());
            });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_ingredients')
            ->withPivot('quantity', 'unit')
            ->withTimestamps();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where('name', 'like', "%{$term}%");
    }

    /**
     * Total stock, expressed in this ingredient's display unit (`unit`),
     * even when individual batches were received in a different-but-compatible
     * unit (e.g. display unit kg, one batch received in g). Each batch is
     * converted to the canonical base unit, summed, then converted back once —
     * no rounding happens here, only when the value is rendered.
     */
    public function getTotalStockAttribute(): float
    {
        $batches = $this->relationLoaded('validBatches')
            ? $this->validBatches
            : $this->validBatches()->get();

        $units = app(UnitConversionService::class);

        $baseSum = $batches->sum(
            fn (IngredientBatch $batch) => $units->normalize((float) $batch->remaining_quantity, $batch->unit ?? $this->unit)
        );

        if ($baseSum === 0.0) {
            return 0.0;
        }

        return $units->convertFromBase($baseSum, $this->measurementType(), $this->unit);
    }

    /**
     * Falls back to deriving the measurement type from `unit` if the
     * `measurement_type` column is somehow null (e.g. a row inserted
     * outside IngredientController::store(), before the backfill migration
     * ran, or via direct DB/seeder access). Self-healing: persists the
     * derived value so this only has to happen once per stale row.
     */
    private function measurementType(): string
    {
        if ($this->measurement_type) {
            return $this->measurement_type;
        }

        $derived = app(UnitConversionService::class)->getMeasurementType($this->unit);
        $this->measurement_type = $derived;

        if ($this->exists) {
            $this->saveQuietly();
        }

        return $derived;
    }

    public function getStatusAttribute(): string
    {
        $stock = $this->total_stock;

        if ($stock <= 0) {
            return 'out_of_stock';
        }

        if ($stock <= (float) $this->minimum_stock) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    public function getNearestExpiryAttribute(): ?string
    {
        $batches = $this->relationLoaded('validBatches')
            ? $this->validBatches
            : $this->validBatches()->get();

        $nearest = $batches->whereNotNull('expiry_date')->sortBy('expiry_date')->first();

        return $nearest?->expiry_date?->toDateString();
    }

    /** Units this ingredient's recipe entries are allowed to use, e.g. weight -> ['g', 'kg']. */
    public function getAllowedRecipeUnitsAttribute(): array
    {
        return app(UnitConversionService::class)->getAllowedUnits($this->measurementType());
    }

    /**
     * Current stock value = total_stock * unit_cost, in the ingredient's
     * display unit. Null (not 0) when unit_cost hasn't been set, so callers
     * can distinguish "no cost tracked" from "worth nothing" and exclude it
     * from stock-value totals rather than treating it as free stock.
     */
    public function getTotalValueAttribute(): ?float
    {
        if ($this->unit_cost === null) {
            return null;
        }

        return round($this->total_stock * $this->unit_cost, 2);
    }
}