<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ShopSetting extends Model
{
    protected $fillable = [
        'shop_name',
        'logo_path',
    ];

    /**
     * Returns the singleton shop settings row, creating a default one
     * (named "JC66 Coffee Shop") on first access.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'shop_name' => 'JC66 Coffee Shop',
        ]);
    }

    /**
     * Public URL for the uploaded logo, or null when no logo is set.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    /**
     * Display name used across the UI and reports. Falls back to the
     * configured application name so reports never render a blank title.
     */
    public static function displayName(): string
    {
        $name = static::current()->shop_name;

        return $name ?: config('app.name', 'JC66 Coffee Shop');
    }
}
