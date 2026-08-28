<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    Droplet,
    Layers,
    Loader2,
    Package,
    PackageX,
    Pencil,
    Plus,
    Scale,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

type MeasurementType = 'weight' | 'volume' | 'piece';
type Unit = 'g' | 'kg' | 'ml' | 'l' | 'pcs';

interface Ingredient {
    id: number;
    name: string;
    measurement_type: MeasurementType;
    unit: Unit;
    minimum_stock: string;
    unit_cost: number | null;
    total_stock: number;
    total_value: number | null;
    status: 'in_stock' | 'low_stock' | 'out_of_stock';
    nearest_expiry: string | null;
    allowed_recipe_units: Unit[];
}

interface Batch {
    id: number;
    unit: Unit;
    quantity: string;
    remaining_quantity: string;
    received_date: string;
    expiry_date: string | null;
    status: 'active' | 'expiring_soon' | 'expired';
    total_cost: number | null;
}

const props = defineProps<{
    ingredients: Ingredient[];
    filters: { search?: string };
}>();
const { isAdmin, canManageInventory } = usePermissions();

const page = usePage<{ flash?: { success?: string; error?: string } }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Ingredients', href: '/inventory/ingredients' }];

/* ---------------------------------------------------------------------- */
/* Measurement type <-> unit mapping (mirrors UnitConversionService)      */
/* ---------------------------------------------------------------------- */

const UNITS_BY_TYPE: Record<MeasurementType, Unit[]> = {
    weight: ['g', 'kg'],
    volume: ['ml', 'l'],
    piece: ['pcs'],
};

const TYPE_LABELS: Record<MeasurementType, string> = {
    weight: 'Weight',
    volume: 'Volume',
    piece: 'Piece',
};

const TYPE_ICON: Record<MeasurementType, typeof Scale> = {
    weight: Scale,
    volume: Droplet,
    piece: Package,
};

const TYPE_BADGE_STYLES: Record<MeasurementType, string> = {
    weight: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    volume: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
    piece: 'bg-amber-500/10 text-amber-600 dark:text-amber-500',
};

function displayUnit(unit: Unit): string {
    return unit === 'l' ? 'L' : unit;
}

function formatStock(value: number, type: MeasurementType): string {
    if (type === 'piece') {
        return Math.round(value).toLocaleString('en-PH');
    }
    // Round to 2 decimals, but drop trailing zeros: 7.0000 -> "7", 2119.25 -> "2,119.25"
    return value.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatCurrency(value: number | null): string {
    if (value === null) return '—';
    return `₱${value.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

// unit_cost is stored as a whole peso amount (no cents), so it's formatted
// without decimals — total_value is a computed total and keeps formatCurrency's decimals.
function formatWholePeso(value: number): string {
    return `₱${Number(value).toLocaleString('en-PH', { maximumFractionDigits: 0 })}`;
}

const statusStyles: Record<Ingredient['status'], string> = {
    in_stock: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400',
    low_stock: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    out_of_stock: 'bg-destructive/10 text-destructive',
};

const statusLabels: Record<Ingredient['status'], string> = {
    in_stock: 'In Stock',
    low_stock: 'Low Stock',
    out_of_stock: 'Out of Stock',
};

/* ---------------------------------------------------------------------- */
/* Notifications                                                          */
/* ---------------------------------------------------------------------- */

const notification = ref<{ type: 'success' | 'error'; message: string } | null>(null);
let notificationTimeout: ReturnType<typeof setTimeout> | null = null;

function showNotification(type: 'success' | 'error', message: string) {
    notification.value = { type, message };

    if (notificationTimeout) clearTimeout(notificationTimeout);
    notificationTimeout = setTimeout(() => {
        notification.value = null;
    }, 3500);
}

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) showNotification('success', flash.success);
        if (flash?.error) showNotification('error', flash.error);
    },
    { deep: true },
);

/* ---------------------------------------------------------------------- */
/* Search + type filter                                                   */
/* ---------------------------------------------------------------------- */

const search = ref(props.filters.search ?? '');
const typeFilter = ref<'' | MeasurementType>('');
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

watch(search, () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        router.get(
            route('inventory.ingredients'),
            { search: search.value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});

const filteredIngredients = computed(() =>
    typeFilter.value ? props.ingredients.filter((i) => i.measurement_type === typeFilter.value) : props.ingredients,
);

/* ---------------------------------------------------------------------- */
/* Summary stats                                                          */
/* ---------------------------------------------------------------------- */

const stats = computed(() => ({
    total: props.ingredients.length,
    lowStock: props.ingredients.filter((i) => i.status === 'low_stock').length,
    outOfStock: props.ingredients.filter((i) => i.status === 'out_of_stock').length,
}));

/* ---------------------------------------------------------------------- */
/* Add / Edit ingredient                                                  */
/* ---------------------------------------------------------------------- */

const isDialogOpen = ref(false);
const isEditing = ref(false);
const editingId = ref<number | null>(null);
const editingHasBatches = ref(false);

const form = useForm({
    name: '',
    measurement_type: 'weight' as MeasurementType,
    unit: 'kg' as Unit,
    minimum_stock: '',
    unit_cost: '',
    // Only used when adding a new ingredient (initial stock).
    quantity: '',
    received_date: new Date().toISOString().slice(0, 10),
    expiry_date: '',
});

// Keep the unit dropdown in sync with measurement type — always land on a
// valid unit for the newly selected type instead of leaving a stale one.
watch(
    () => form.measurement_type,
    (type) => {
        if (!UNITS_BY_TYPE[type].includes(form.unit)) {
            form.unit = UNITS_BY_TYPE[type][UNITS_BY_TYPE[type].length - 1];
        }
    },
);

function openAddDialog() {
    isEditing.value = false;
    editingId.value = null;
    editingHasBatches.value = false;
    form.reset();
    form.clearErrors();
    form.measurement_type = 'weight';
    form.unit = 'kg';
    form.received_date = new Date().toISOString().slice(0, 10);
    isDialogOpen.value = true;
}

function openEditDialog(ingredient: Ingredient) {
    isEditing.value = true;
    editingId.value = ingredient.id;
    editingHasBatches.value = ingredient.total_stock > 0 || !!ingredient.nearest_expiry;
    form.name = ingredient.name;
    form.measurement_type = ingredient.measurement_type;
    form.unit = ingredient.unit;
    form.minimum_stock = ingredient.minimum_stock;
    form.unit_cost = ingredient.unit_cost !== null ? String(ingredient.unit_cost) : '';
    // Stock on an existing ingredient always goes through Restock, not this dialog.
    form.quantity = '';
    form.received_date = '';
    form.expiry_date = '';
    form.clearErrors();
    isDialogOpen.value = true;
}

function closeDialog() {
    isDialogOpen.value = false;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (isEditing.value && editingId.value) {
        form.put(route('inventory.ingredients.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });
    } else {
        form.post(route('inventory.ingredients.store'), {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });
    }
}

/* ---------------------------------------------------------------------- */
/* Delete                                                                  */
/* ---------------------------------------------------------------------- */

const deleteTarget = ref<Ingredient | null>(null);
const isDeleting = ref(false);

function deleteIngredient() {
    if (!deleteTarget.value) return;
    isDeleting.value = true;
    router.delete(route('inventory.ingredients.destroy', String(deleteTarget.value.id)), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            deleteTarget.value = null;
        },
    });
}

/* ---------------------------------------------------------------------- */
/* Manager delete request (sent to Admin for approval)                    */
/* ---------------------------------------------------------------------- */

const requestTarget = ref<Ingredient | null>(null);
const requestForm = useForm({
    type: 'ingredient_deletion',
    reason: '',
    target_type: 'ingredient',
    target_id: 0,
});

function onDeleteClick(ingredient: Ingredient) {
    // Admins delete immediately; managers must request Admin approval.
    if (isAdmin.value) {
        deleteTarget.value = ingredient;
    } else {
        requestTarget.value = ingredient;
    }
}

function closeRequest() {
    requestTarget.value = null;
    requestForm.reset();
    requestForm.clearErrors();
}

function submitDeleteRequest() {
    if (!requestTarget.value) return;
    requestForm.target_id = requestTarget.value.id;
    if (!requestForm.reason) {
        requestForm.reason = `Request deletion of ingredient "${requestTarget.value.name}".`;
    }
    requestForm.post(route('action-requests.store'), {
        preserveScroll: true,
        onSuccess: () => closeRequest(),
    });
}

/* ---------------------------------------------------------------------- */
/* Batches panel                                                          */
/* ---------------------------------------------------------------------- */

const batchesTarget = ref<Ingredient | null>(null);
const batches = ref<Batch[]>([]);
const isLoadingBatches = ref(false);

async function openBatches(ingredient: Ingredient) {
    batchesTarget.value = ingredient;
    isLoadingBatches.value = true;
    try {
        const res = await fetch(route('inventory.ingredients.batches', String(ingredient.id)));
        const data = await res.json();
        batches.value = data.batches;
    } finally {
        isLoadingBatches.value = false;
    }
}

/* ---------------------------------------------------------------------- */
/* Restock                                                                 */
/* ---------------------------------------------------------------------- */

const isRestockOpen = ref(false);
const restockForm = useForm({
    quantity: '',
    unit: 'g' as Unit,
    received_date: new Date().toISOString().slice(0, 10),
    expiry_date: '',
    total_cost: '',
});

// A batch can be received in any unit compatible with the ingredient's
// measurement type (e.g. display unit kg, delivery invoiced in g) — never
// an incompatible one (no L option on a weight ingredient).
const restockUnitOptions = computed(() =>
    batchesTarget.value ? UNITS_BY_TYPE[batchesTarget.value.measurement_type] : [],
);

// Cost of the existing (old) stock before this restock, and the cost of the
// new batch being entered — both surfaced in the Restock dialog so the user
// can see what they're adding against what's already on hand.
const oldStockValue = computed(() => batchesTarget.value?.total_value ?? 0);
const newBatchCost = computed(() => (restockForm.total_cost ? Number(restockForm.total_cost) : 0));
const projectedStockValue = computed(() => oldStockValue.value + newBatchCost.value);

function openRestock(ingredient: Ingredient) {
    batchesTarget.value = ingredient;
    restockForm.reset();
    restockForm.unit = ingredient.unit;
    restockForm.received_date = new Date().toISOString().slice(0, 10);
    isRestockOpen.value = true;
}

function submitRestock() {
    if (!batchesTarget.value) return;
    restockForm.post(route('inventory.ingredients.restock', String(batchesTarget.value.id)), {
        preserveScroll: true,
        onSuccess: () => {
            isRestockOpen.value = false;
            openBatches(batchesTarget.value!);
        },
    });
}

const batchStatusStyles: Record<Batch['status'], string> = {
    active: 'bg-muted text-muted-foreground',
    expiring_soon: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    expired: 'bg-destructive/10 text-destructive',
};

const hasIngredients = computed(() => filteredIngredients.value.length > 0);
</script>

<template>
    <Head title="Ingredients" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Toast notification -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="notification"
                class="fixed right-4 top-4 z-50 rounded-lg border px-4 py-3 text-sm shadow-lg"
                :class="
                    notification.type === 'success'
                        ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300'
                        : 'border-destructive/30 bg-destructive/10 text-destructive'
                "
            >
                {{ notification.message }}
            </div>
        </Transition>

        <div class="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Ingredients</h1>
                    <p class="text-sm text-foreground/60">Stock is tracked in each ingredient's own display unit.</p>
                </div>
                <Button @click="openAddDialog">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Ingredient
                </Button>
            </div>

            <!-- Summary stat cards -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-muted">
                        <Boxes class="h-5 w-5 text-foreground/60" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold leading-none text-foreground">{{ stats.total }}</p>
                        <p class="text-xs text-foreground/60">Ingredients tracked</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/10">
                        <AlertTriangle class="h-5 w-5 text-amber-600 dark:text-amber-500" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold leading-none text-foreground">{{ stats.lowStock }}</p>
                        <p class="text-xs text-foreground/60">Low stock</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10">
                        <PackageX class="h-5 w-5 text-destructive" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold leading-none text-foreground">{{ stats.outOfStock }}</p>
                        <p class="text-xs text-foreground/60">Out of stock</p>
                    </div>
                </div>
            </div>

            <!-- Search + type filter -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground/60" />
                    <Input v-model="search" type="text" placeholder="Search ingredients..." class="pl-9" />
                </div>

                <div class="flex gap-1.5">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="typeFilter === '' ? 'bg-foreground text-background' : 'bg-muted text-foreground/60 hover:bg-muted/70'"
                        @click="typeFilter = ''"
                    >
                        All
                    </button>
                    <button
                        v-for="type in (['weight', 'volume', 'piece'] as MeasurementType[])"
                        :key="type"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="typeFilter === type ? 'bg-foreground text-background' : 'bg-muted text-foreground/60 hover:bg-muted/70'"
                        @click="typeFilter = type"
                    >
                        {{ TYPE_LABELS[type] }}
                    </button>
                </div>
            </div>

            <!-- Inventory Data Table inside a Content Card -->
            <div
                v-if="hasIngredients"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-foreground/70">Ingredient</th>
                                <th class="px-4 py-3 text-left font-medium text-foreground/70">Type</th>
                                <th class="px-4 py-3 text-right font-medium text-foreground/70">Stock</th>
                                <th class="px-4 py-3 text-right font-medium text-foreground/70">Min. Stock</th>
                                <th class="px-4 py-3 text-right font-medium text-foreground/70">Cost / Value</th>
                                <th class="px-4 py-3 text-left font-medium text-foreground/70">Expiry</th>
                                <th class="px-4 py-3 text-left font-medium text-foreground/70">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-foreground/70">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="ingredient in filteredIngredients"
                                :key="ingredient.id"
                                class="border-b border-sidebar-border/70 transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                            >
                                <!-- Name + type icon -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                            :class="TYPE_BADGE_STYLES[ingredient.measurement_type]"
                                        >
                                            <component :is="TYPE_ICON[ingredient.measurement_type]" class="h-4 w-4" />
                                        </div>
                                        <span class="font-medium text-foreground">{{ ingredient.name }}</span>
                                    </div>
                                </td>

                                <!-- Type -->
                                <td class="px-4 py-3 text-foreground/60">
                                    {{ TYPE_LABELS[ingredient.measurement_type] }}
                                </td>

                                <!-- Stock -->
                                <td class="px-4 py-3 text-right font-medium text-foreground">
                                    {{ formatStock(ingredient.total_stock, ingredient.measurement_type) }}
                                    {{ displayUnit(ingredient.unit) }}
                                </td>

                                <!-- Minimum stock -->
                                <td class="px-4 py-3 text-right text-foreground/60">
                                    {{ formatStock(Number(ingredient.minimum_stock), ingredient.measurement_type) }}{{ displayUnit(ingredient.unit) }}
                                </td>

                                <!-- Cost / value -->
                                <td class="px-4 py-3 text-right text-foreground/60">
                                    <template v-if="ingredient.unit_cost">
                                        {{ formatWholePeso(ingredient.unit_cost) }}/{{ displayUnit(ingredient.unit) }}
                                        <br />
                                        <span class="text-xs">{{ formatCurrency(ingredient.total_value) }}</span>
                                    </template>
                                    <template v-else>—</template>
                                </td>

                                <!-- Expiry -->
                                <td class="px-4 py-3">
                                    <span
                                        v-if="ingredient.nearest_expiry"
                                        class="flex items-center gap-1 whitespace-nowrap text-xs text-foreground/60"
                                    >
                                        <AlertTriangle v-if="ingredient.status !== 'out_of_stock'" class="h-3.5 w-3.5" />
                                        {{ ingredient.nearest_expiry }}
                                    </span>
                                    <span v-else class="text-xs text-foreground/60">—</span>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="statusStyles[ingredient.status]"
                                    >
                                        {{ statusLabels[ingredient.status] }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <Button variant="outline" size="icon" title="View batches" @click="openBatches(ingredient)" class="text-foreground/60 hover:text-foreground">
                                            <Layers class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            v-if="canManageInventory"
                                            variant="outline"
                                            size="icon"
                                            title="Restock"
                                            @click="openRestock(ingredient)"
                                            class="text-foreground/60 hover:text-foreground"
                                        >
                                            <Plus class="h-4 w-4" />
                                        </Button>
                                        <Button variant="outline" size="icon" title="Edit" @click="openEditDialog(ingredient)" class="text-foreground/60 hover:text-foreground">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            v-if="canManageInventory"
                                            variant="outline"
                                            size="icon"
                                            title="Delete"
                                            @click="onDeleteClick(ingredient)"
                                            class="text-foreground/60 hover:text-foreground"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex min-h-[250px] flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 text-center text-foreground/60 dark:border-sidebar-border"
            >
                <p>No ingredients match. Add your first ingredient to get started.</p>
                <Button size="sm" @click="openAddDialog">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Ingredient
                </Button>
            </div>
        </div>

        <!-- Add / Edit Ingredient -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit Ingredient' : 'Add Ingredient' }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <!-- Name -->
                    <div class="space-y-2">
                        <Label for="ing-name">Ingredient Name</Label>
                        <Input id="ing-name" v-model="form.name" type="text" placeholder="e.g. Arabica Coffee Beans" />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <!-- Measurement Type -->
                    <div class="space-y-2">
                        <Label for="ing-type">Measurement Type</Label>
                        <select
                            id="ing-type"
                            v-model="form.measurement_type"
                            :disabled="editingHasBatches"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-60"
                        >
                            <option value="weight">Weight (kg / g)</option>
                            <option value="volume">Volume (L / ml)</option>
                            <option value="piece">Piece (pcs)</option>
                        </select>
                        <p v-if="editingHasBatches" class="text-xs text-foreground/60">
                            Locked — this ingredient already has stock batches.
                        </p>
                        <p v-if="form.errors.measurement_type" class="text-sm text-destructive">{{ form.errors.measurement_type }}</p>
                    </div>

                    <!-- Display Unit -->
                    <div class="space-y-2">
                        <Label for="ing-unit">Display Unit</Label>
                        <select
                            id="ing-unit"
                            v-model="form.unit"
                            :disabled="editingHasBatches"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-60"
                        >
                            <option v-for="u in UNITS_BY_TYPE[form.measurement_type]" :key="u" :value="u">
                                {{ displayUnit(u) }}
                            </option>
                        </select>
                        <p class="text-xs text-foreground/60">
                            This is how stock is stored and shown — it does not need to match recipe units.
                        </p>
                        <p v-if="form.errors.unit" class="text-sm text-destructive">{{ form.errors.unit }}</p>
                    </div>

                    <!-- Minimum Stock -->
                    <div class="space-y-2">
                        <Label for="ing-min">Minimum Stock / Reorder Level</Label>
                        <div class="flex items-center gap-2">
                            <Input id="ing-min" v-model="form.minimum_stock" type="number" step="0.01" min="0" class="flex-1" />
                            <span class="w-10 shrink-0 text-sm text-foreground/60">{{ displayUnit(form.unit) }}</span>
                        </div>
                        <p v-if="form.errors.minimum_stock" class="text-sm text-destructive">{{ form.errors.minimum_stock }}</p>
                    </div>

                    <!-- Cost per unit -->
                    <div class="space-y-2">
                        <Label for="ing-cost">Cost per {{ displayUnit(form.unit) }} (optional)</Label>
                        <div class="flex items-center gap-2">
                            <span class="shrink-0 text-sm text-foreground/60">₱</span>
                            <Input id="ing-cost" v-model="form.unit_cost" type="number" step="1" min="0" placeholder="0" class="flex-1" />
                        </div>
                        <p class="text-xs text-foreground/60">
                            Whole pesos only. Used to compute this ingredient's stock value on the Inventory Report. Leave blank if you don't track cost.
                        </p>
                        <p v-if="form.errors.unit_cost" class="text-sm text-destructive">{{ form.errors.unit_cost }}</p>
                    </div>

                    <!-- Initial Stock (create only) -->
                    <div v-if="!isEditing" class="space-y-2 rounded-md border border-input p-3">
                        <Label for="ing-qty">Initial Stock (optional)</Label>
                        <p class="text-xs text-foreground/60">
                            Leave blank to add this ingredient with zero stock and restock later.
                        </p>

                        <div class="flex items-center gap-2">
                            <Input id="ing-qty" v-model="form.quantity" type="number" step="0.01" min="0" placeholder="0" class="flex-1" />
                            <span class="w-10 shrink-0 text-sm text-foreground/60">{{ displayUnit(form.unit) }}</span>
                        </div>
                        <p v-if="form.errors.quantity" class="text-sm text-destructive">{{ form.errors.quantity }}</p>

                        <div v-if="form.quantity" class="grid grid-cols-2 gap-3 pt-1">
                            <div class="space-y-1">
                                <Label for="ing-received" class="text-xs">Received Date</Label>
                                <Input id="ing-received" v-model="form.received_date" type="date" class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Label for="ing-expiry" class="text-xs">Expiry Date (optional)</Label>
                                <Input id="ing-expiry" v-model="form.expiry_date" type="date" class="h-8" />
                                <p v-if="form.errors.expiry_date" class="text-xs text-destructive">{{ form.errors.expiry_date }}</p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closeDialog">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            {{ isEditing ? 'Save Changes' : 'Add Ingredient' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Batches panel -->
        <Dialog :open="!!batchesTarget && !isRestockOpen" @update:open="(val) => !val && (batchesTarget = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ batchesTarget?.name }} — Batches (FEFO)</DialogTitle>
                </DialogHeader>

                <div v-if="isLoadingBatches" class="flex justify-center py-8">
                    <Loader2 class="h-5 w-5 animate-spin text-foreground/60" />
                </div>

                <div v-else class="max-h-96 space-y-2 overflow-auto">
                    <div v-for="batch in batches" :key="batch.id" class="rounded-md border p-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-foreground">Batch #{{ batch.id }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="batchStatusStyles[batch.status]">
                                {{ batch.status.replace('_', ' ') }}
                            </span>
                        </div>
                        <p class="mt-1 text-foreground/60">
                            Quantity: {{ formatStock(Number(batch.quantity), batchesTarget!.measurement_type) }}{{ displayUnit(batch.unit) }} · Remaining:
                            {{ formatStock(Number(batch.remaining_quantity), batchesTarget!.measurement_type) }}{{ displayUnit(batch.unit) }}
                        </p>
                        <p class="text-foreground/60">
                            Received: {{ batch.received_date }}
                            <span v-if="batch.expiry_date"> · Expires: {{ batch.expiry_date }}</span>
                        </p>
                        <p v-if="batch.total_cost !== null" class="text-foreground/60">
                            Cost: {{ formatWholePeso(batch.total_cost) }}
                        </p>
                    </div>
                    <p v-if="batches.length === 0" class="py-6 text-center text-foreground/60">No batches yet.</p>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Restock -->
        <Dialog v-model:open="isRestockOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Restock — {{ batchesTarget?.name }}</DialogTitle>
                </DialogHeader>

                <!-- Old vs. new stock cost summary -->
                <div class="space-y-1.5 rounded-md border border-input bg-muted/30 p-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-foreground/60">Current stock (old):</span>
                        <span class="font-medium text-foreground">
                            {{ formatStock(Number(batchesTarget!.total_stock), batchesTarget!.measurement_type)
                            }}{{ displayUnit(batchesTarget!.unit) }}
                            · {{ formatCurrency(oldStockValue) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-foreground/60">New stock cost:</span>
                        <span class="font-medium text-foreground">{{ formatCurrency(newBatchCost) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-input pt-1.5">
                        <span class="text-foreground/60">Projected total value:</span>
                        <span class="font-semibold text-foreground">{{ formatCurrency(projectedStockValue) }}</span>
                    </div>
                </div>

                <form class="space-y-4" @submit.prevent="submitRestock">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <Label for="rs-qty">Quantity</Label>
                            <Input id="rs-qty" v-model="restockForm.quantity" type="number" step="0.01" min="0.01" />
                            <p v-if="restockForm.errors.quantity" class="text-sm text-destructive">{{ restockForm.errors.quantity }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="rs-unit">Unit</Label>
                            <select
                                id="rs-unit"
                                v-model="restockForm.unit"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option v-for="u in restockUnitOptions" :key="u" :value="u">{{ displayUnit(u) }}</option>
                            </select>
                            <p class="text-xs text-foreground/60">Only units compatible with this ingredient are shown.</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="rs-received">Received Date</Label>
                        <Input id="rs-received" v-model="restockForm.received_date" type="date" />
                    </div>

                    <div class="space-y-2">
                        <Label for="rs-expiry">Expiry Date (optional)</Label>
                        <Input id="rs-expiry" v-model="restockForm.expiry_date" type="date" />
                        <p v-if="restockForm.errors.expiry_date" class="text-sm text-destructive">{{ restockForm.errors.expiry_date }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="rs-cost">Total Cost (optional)</Label>
                        <div class="flex items-center gap-2">
                            <span class="shrink-0 text-sm text-foreground/60">₱</span>
                            <Input id="rs-cost" v-model="restockForm.total_cost" type="number" step="1" min="0" placeholder="0" class="flex-1" />
                        </div>
                        <p class="text-xs text-foreground/60">
                            How much you paid for this whole delivery, in whole pesos. Shown on the Restock History.
                        </p>
                        <p v-if="restockForm.errors.total_cost" class="text-sm text-destructive">{{ restockForm.errors.total_cost }}</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="isRestockOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="restockForm.processing">
                            <Loader2 v-if="restockForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Add Batch
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation -->
        <Dialog :open="!!deleteTarget" @update:open="(val) => !val && (deleteTarget = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Ingredient</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-foreground/60">
                    Are you sure you want to delete "{{ deleteTarget?.name }}"? This cannot be undone.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="deleteTarget = null">Cancel</Button>
                    <Button variant="destructive" :disabled="isDeleting" @click="deleteIngredient">
                        <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Manager: Request Deletion (Admin approval) -->
        <Dialog :open="!!requestTarget" @update:open="(val) => !val && closeRequest()">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Request Ingredient Deletion</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-foreground/70">
                    Submit a deletion request for "{{ requestTarget?.name }}" to an Admin for approval?
                </p>
                <div class="space-y-2">
                    <Label for="del-reason">Reason (optional)</Label>
                    <textarea
                        id="del-reason"
                        v-model="requestForm.reason"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        placeholder="Why should this ingredient be deleted?"
                    ></textarea>
                    <p v-if="requestForm.errors.reason" class="text-sm text-destructive">{{ requestForm.errors.reason }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="closeRequest">Cancel</Button>
                    <Button variant="destructive" :disabled="requestForm.processing" @click="submitDeleteRequest">
                        <Loader2 v-if="requestForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Send Request
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>