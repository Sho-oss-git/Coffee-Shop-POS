<?php

namespace App\Services;

use InvalidArgumentException;

class UnitConversionService
{
    private const UNIT_TYPES = [
        'g' => 'weight',
        'kg' => 'weight',
        'ml' => 'volume',
        'l' => 'volume',
        'pcs' => 'piece',
        'pc' => 'piece',
    ];

    private const FACTORS = [
        'g' => 1.0,
        'kg' => 1000.0,
        'ml' => 1.0,
        'l' => 1000.0,
        'pcs' => 1.0,
        'pc' => 1.0,
    ];

    private const BASE_UNITS = [
        'weight' => 'g',
        'volume' => 'ml',
        'piece' => 'pcs',
    ];

    private const ALLOWED_UNITS = [
        'weight' => ['g', 'kg'],
        'volume' => ['ml', 'l'],
        'piece' => ['pcs', 'pc'],
    ];

    public function getMeasurementType(string $unit): string
    {
        $key = strtolower(trim($unit));

        if (! isset(self::UNIT_TYPES[$key])) {
            throw new InvalidArgumentException("Unknown unit [{$unit}]. Supported units: ".implode(', ', array_keys(self::UNIT_TYPES)));
        }

        return self::UNIT_TYPES[$key];
    }

    public function validateCompatibleUnits(string $unitA, string $unitB): bool
    {
        return $this->getMeasurementType($unitA) === $this->getMeasurementType($unitB);
    }

    public function normalize(float $amount, string $unit): float
    {
        $key = strtolower(trim($unit));
        $this->getMeasurementType($unit);

        return $amount * self::FACTORS[$key];
    }

    public function convertFromBase(float $baseAmount, string $measurementType, string $toUnit): float
    {
        if (! isset(self::BASE_UNITS[$measurementType])) {
            throw new InvalidArgumentException("Unknown measurement type [{$measurementType}].");
        }

        if ($this->getMeasurementType($toUnit) !== $measurementType) {
            throw new InvalidArgumentException(
                "Cannot convert base [{$measurementType}] amount into incompatible unit [{$toUnit}]."
            );
        }

        $key = strtolower(trim($toUnit));

        return $baseAmount / self::FACTORS[$key];
    }

    public function convert(float $amount, string $fromUnit, string $toUnit): float
    {
        if (! $this->validateCompatibleUnits($fromUnit, $toUnit)) {
            throw new InvalidArgumentException(
                "Incompatible units: cannot convert [{$fromUnit}] to [{$toUnit}]."
            );
        }

        $type = $this->getMeasurementType($fromUnit);

        return $this->convertFromBase($this->normalize($amount, $fromUnit), $type, $toUnit);
    }

    public function getBaseUnit(string $measurementType): string
    {
        if (! isset(self::BASE_UNITS[$measurementType])) {
            throw new InvalidArgumentException("Unknown measurement type [{$measurementType}].");
        }

        return self::BASE_UNITS[$measurementType];
    }

    public function getAllowedUnits(string $measurementType): array
    {
        if (! isset(self::ALLOWED_UNITS[$measurementType])) {
            throw new InvalidArgumentException("Unknown measurement type [{$measurementType}].");
        }

        return self::ALLOWED_UNITS[$measurementType];
    }

    public function costPerBaseUnit(\App\Models\Ingredient $ingredient): ?float
    {
        if ($ingredient->unit_cost === null) {
            return null;
        }

        if ($ingredient->unit === 'pcs' || $ingredient->unit === 'pc') {
            return (float) $ingredient->unit_cost;
        }

        $oneDisplayUnitInBase = $this->normalize(1, $ingredient->unit);

        if ($oneDisplayUnitInBase <= 0) {
            return null;
        }

        return (float) $ingredient->unit_cost / $oneDisplayUnitInBase;
    }

    public function getDefaultRecipeUnit(string $measurementType): string
    {
        return self::ALLOWED_UNITS[$measurementType][0]
            ?? throw new InvalidArgumentException("Unknown measurement type [{$measurementType}].");
    }

    public function formatForDisplay(float $amount, string $unit): string
    {
        $decimals = $this->getMeasurementType($unit) === 'piece' ? 0 : 2;

        return number_format($amount, $decimals, '.', '').$unit;
    }
}