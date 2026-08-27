<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { ImageOff, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Category, Ingredient } from '../types';

/**
 * NOTE: `Ingredient` (imported from '../types') needs two new fields to match
 * the backend response — add these to resources/js/pages/Products/types.ts:
 *
 *   measurement_type: 'weight' | 'volume' | 'piece';
 *   allowed_recipe_units: string[]; // e.g. ['g', 'kg'] — from Ingredient::allowed_recipe_units
 *
 * The recipe's unit is intentionally independent from the ingredient's
 * inventory display unit — e.g. an ingredient stocked in kg can still have a
 * recipe entry in g. The backend (UnitConversionService) converts between
 * them at consumption time; this dropdown just restricts choices to units
 * that are valid for the ingredient's measurement type.
 */

const props = defineProps<{
    form: any; // Inertia useForm instance, mutated directly by design
    categories: Category[];
    ingredients: Ingredient[];
    imagePreview: string | null;
}>();

const emit = defineEmits<{
    (e: 'change-image', file: File | null): void;
    (e: 'manage-categories'): void;
}>();

function onImageInput(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    emit('change-image', file);
}

function findIngredient(ingredientId: number | string): Ingredient | undefined {
    return props.ingredients.find((i) => i.id === Number(ingredientId));
}

/** Units this ingredient's recipe line is allowed to use, e.g. weight -> ['g', 'kg']. */
function recipeUnitOptions(ingredientId: number | string): string[] {
    return (findIngredient(ingredientId) as (Ingredient & { allowed_recipe_units?: string[] }) | undefined)
        ?.allowed_recipe_units ?? [];
}

/** The smaller, practical recipe unit — g for weight, ml for volume, pcs for piece. */
function defaultRecipeUnit(ingredientId: number | string): string {
    const options = recipeUnitOptions(ingredientId);
    return options[0] ?? '';
}

const usedIngredientIds = computed(
    () => new Set(props.form.ingredients.map((i: { ingredient_id: number }) => i.ingredient_id)),
);

function availableIngredients(currentId: number) {
    return props.ingredients.filter((i) => i.id === currentId || !usedIngredientIds.value.has(i.id));
}

function addRecipeIngredient() {
    const next = props.ingredients.find((i) => !usedIngredientIds.value.has(i.id));
    if (!next) return;

    props.form.ingredients.push({
        ingredient_id: next.id,
        quantity: '',
        unit: defaultRecipeUnit(next.id),
    });
}

function removeRecipeIngredient(index: number) {
    props.form.ingredients.splice(index, 1);
}

function onRecipeIngredientChange(item: { ingredient_id: string | number; unit: string }) {
    item.ingredient_id = Number(item.ingredient_id);
    // Switching ingredients resets the unit to that ingredient's default
    // recipe unit, since the old unit may not even be valid for the new type.
    item.unit = defaultRecipeUnit(item.ingredient_id);
}

const canAddIngredient = computed(() => usedIngredientIds.value.size < props.ingredients.length);
</script>

<template>
    <div class="space-y-2">
        <Label for="image">Product Image</Label>
        <div class="flex items-center gap-3">
            <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-md bg-amber-100 dark:bg-amber-950/40">
                <img v-if="imagePreview" :src="imagePreview" alt="Preview" class="h-full w-full object-cover" />
                <ImageOff v-else class="h-6 w-6 text-amber-400 dark:text-amber-700" />
            </div>
            <Input id="image" type="file" accept="image/*" @change="onImageInput" />
        </div>
        <p v-if="form.errors.image" class="text-sm text-destructive">{{ form.errors.image }}</p>
    </div>

    <div class="space-y-2">
        <Label for="name">Product Name</Label>
        <Input id="name" v-model="form.name" type="text" placeholder="e.g. Iced Caramel Latte" />
        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
    </div>

    <div class="space-y-2">
        <Label for="category">Category</Label>
        <div class="flex gap-2">
            <select
                id="category"
                v-model="form.category"
                class="h-9 flex-1 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
            >
                <option value="" disabled>Select Category</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
            </select>
            <Button type="button" variant="outline" size="icon" title="Manage categories" @click="emit('manage-categories')">
                <Plus class="h-4 w-4" />
            </Button>
        </div>
        <p v-if="categories.length === 0" class="text-sm text-foreground/60">
            No categories yet — click the + button to add one.
        </p>
        <p v-if="form.errors.category" class="text-sm text-destructive">{{ form.errors.category }}</p>
    </div>

    <div class="space-y-2">
        <Label for="price">Selling Price</Label>
        <Input id="price" v-model="form.price" type="number" step="0.01" min="0" placeholder="0.00" />
        <p v-if="form.errors.price" class="text-sm text-destructive">{{ form.errors.price }}</p>
    </div>

    <div class="flex items-center justify-between rounded-md border border-input px-3 py-2">
        <div>
            <Label>Availability</Label>
            <p class="text-xs text-foreground/60">Unavailable products stay listed but can't be sold.</p>
        </div>
        <button
            type="button"
            role="switch"
            :aria-checked="form.is_available"
            class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
            :class="form.is_available ? 'bg-green-600' : 'bg-muted-foreground/30'"
            @click="form.is_available = !form.is_available"
        >
            <span
                class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                :class="form.is_available ? 'translate-x-5' : 'translate-x-0.5'"
            />
        </button>
    </div>
    <p v-if="form.errors.is_available" class="text-sm text-destructive">{{ form.errors.is_available }}</p>

    <div class="space-y-2">
        <Label for="tracking-type">Inventory Tracking</Label>
        <select
            id="tracking-type"
            v-model="form.tracking_type"
            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
        >
            <option value="recipe">Recipe-based (deduct raw ingredients)</option>
            <option value="finished_stock">Finished stock (deduct pieces, e.g. cookies)</option>
        </select>
        <p v-if="form.errors.tracking_type" class="text-sm text-destructive">{{ form.errors.tracking_type }}</p>
    </div>

    <!-- Finished-stock only fields: quantity on hand + manual cost price. -->
    <template v-if="form.tracking_type === 'finished_stock'">
        <div class="space-y-2">
            <Label for="stock-quantity">Stock Quantity (pcs)</Label>
            <Input id="stock-quantity" v-model="form.stock_quantity" type="number" min="0" placeholder="0" />
            <p v-if="form.errors.stock_quantity" class="text-sm text-destructive">{{ form.errors.stock_quantity }}</p>
        </div>

        <div class="space-y-2">
            <Label for="cost-price">Cost Price (per piece)</Label>
            <Input id="cost-price" v-model="form.cost_price" type="number" step="0.01" min="0" placeholder="0.00" />
            <p class="text-xs text-foreground/60">
                Manual cost since finished-stock products have no recipe to calculate it from.
            </p>
            <p v-if="form.errors.cost_price" class="text-sm text-destructive">{{ form.errors.cost_price }}</p>
        </div>
    </template>

    <div v-if="form.tracking_type === 'recipe'" class="space-y-2">
        <Label>Ingredients / Recipe</Label>
        <p class="text-xs text-foreground/60">
            Enter the amount needed for ONE product. This can be a different unit than how the
            ingredient is stocked — e.g. stocked in kg, recipe in g.
        </p>

        <div v-for="(item, index) in form.ingredients" :key="index" class="flex items-end gap-2">
            <select
                v-model="item.ingredient_id"
                class="h-9 flex-1 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                @change="onRecipeIngredientChange(item)"
            >
                <option v-for="ing in availableIngredients(item.ingredient_id)" :key="ing.id" :value="ing.id">
                    {{ ing.name }}
                </option>
            </select>

            <Input v-model="item.quantity" type="number" step="0.01" min="0.01" placeholder="Qty" class="w-24" />

            <select
                v-model="item.unit"
                class="h-9 w-20 shrink-0 rounded-md border border-input bg-background px-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
            >
                <option v-for="u in recipeUnitOptions(item.ingredient_id)" :key="u" :value="u">
                    {{ u === 'l' ? 'L' : u }}
                </option>
            </select>

            <Button type="button" variant="outline" size="icon" @click="removeRecipeIngredient(index)">
                <Trash2 class="h-4 w-4" />
            </Button>
        </div>

        <p v-if="form.ingredients.length === 0" class="text-sm text-foreground/60">No ingredients added yet.</p>

        <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="!canAddIngredient || ingredients.length === 0"
            @click="addRecipeIngredient"
        >
            <Plus class="mr-2 h-4 w-4" />
            Add Ingredient
        </Button>

        <p v-if="ingredients.length === 0" class="text-sm text-foreground/60">
            No ingredients exist yet. Add ingredients from the Inventory page first.
        </p>
        <p v-if="form.errors.ingredients" class="text-sm text-destructive">{{ form.errors.ingredients }}</p>
    </div>
</template>