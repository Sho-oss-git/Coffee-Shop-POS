import { Droplet, Package, Scale } from 'lucide-vue-next';

export type MeasurementType = 'weight' | 'volume' | 'piece';
export type Unit = 'g' | 'kg' | 'ml' | 'l' | 'pcs';

export interface Ingredient {
    id: number;
    name: string;
    measurement_type: MeasurementType;
    unit: Unit;
    minimum_stock: string;
    total_stock: number;
    status: 'in_stock' | 'low_stock' | 'out_of_stock';
    nearest_expiry: string | null;
    allowed_recipe_units: Unit[];
}

export interface Batch {
    id: number;
    unit: Unit;
    quantity: string;
    remaining_quantity: string;
    received_date: string;
    expiry_date: string | null;
    status: 'active' | 'expiring_soon' | 'expired';
}

/* ---------------------------------------------------------------------- */
/* Measurement type <-> unit mapping (mirrors UnitConversionService)      */
/* ---------------------------------------------------------------------- */

export const UNITS_BY_TYPE: Record<MeasurementType, Unit[]> = {
    weight: ['g', 'kg'],
    volume: ['ml', 'l'],
    piece: ['pcs'],
};

export const TYPE_LABELS: Record<MeasurementType, string> = {
    weight: 'Weight',
    volume: 'Volume',
    piece: 'Piece',
};

export const TYPE_ICON: Record<MeasurementType, typeof Scale> = {
    weight: Scale,
    volume: Droplet,
    piece: Package,
};

export const TYPE_BADGE_STYLES: Record<MeasurementType, string> = {
    weight: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    volume: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
    piece: 'bg-amber-500/10 text-amber-600 dark:text-amber-500',
};

export const statusStyles: Record<Ingredient['status'], string> = {
    in_stock: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400',
    low_stock: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    out_of_stock: 'bg-destructive/10 text-destructive',
};

export const statusLabels: Record<Ingredient['status'], string> = {
    in_stock: 'In Stock',
    low_stock: 'Low Stock',
    out_of_stock: 'Out of Stock',
};

export const batchStatusStyles: Record<Batch['status'], string> = {
    active: 'bg-muted text-muted-foreground',
    expiring_soon: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    expired: 'bg-destructive/10 text-destructive',
};

export function displayUnit(unit: Unit): string {
    return unit === 'l' ? 'L' : unit;
}

export function formatStock(value: number, type: MeasurementType): string {
    return type === 'piece' ? String(Math.round(value)) : value.toFixed(2);
}

// An ingredient's type/unit is locked once it has stock or a recorded expiry —
// changing the unit under existing batches would corrupt stock math.
export function hasExistingBatches(ingredient: Ingredient): boolean {
    return ingredient.total_stock > 0 || !!ingredient.nearest_expiry;
}