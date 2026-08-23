<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ChevronDown, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface LowStockItem {
    id: number;
    name: string;
    total_stock: number;
    minimum_stock: number;
    unit: string;
}

interface OutOfStockItem {
    id: number;
    name: string;
    unit: string;
}

interface StockValueItem {
    id: number;
    name: string;
    total_stock: number;
    unit: string;
    unit_cost: number;
    total_value: number;
}

interface ExpiringBatch {
    id: number;
    ingredient_name: string;
    remaining_quantity: number;
    unit: string;
    expiry_date: string;
}

interface RestockLogItem {
    id: number;
    ingredient_name: string;
    unit: string;
    quantity_change: number;
    received_date: string | null;
    expiry_date: string | null;
    price: number | null;
    note: string | null;
    created_at: string;
}

interface ProductRestockLogItem {
    id: number;
    product_name: string;
    type: 'restock' | 'sale' | 'adjustment' | 'expired';
    quantity_change: number;
    note: string | null;
    created_at: string;
}

interface Summary {
    total_ingredients: number;
    total_stock_value: number | null;
    low_stock_count: number;
    expiring_soon_count: number;
    out_of_stock_count: number;
}

const props = defineProps<{
    summary: Summary;
    lowStockItems: LowStockItem[];
    outOfStockItems: OutOfStockItem[];
    expiringSoon: ExpiringBatch[];
    restockHistory: RestockLogItem[];
    productRestockHistory: ProductRestockLogItem[];
    stockValueItems: StockValueItem[];
}>();

function formatCurrency(value: number | null) {
    if (value === null) return '—';
    return `₱${value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// unit_cost is a whole-peso amount (no cents), unlike total_value which is
// a computed total and keeps formatCurrency's decimals.
function formatWholePeso(value: number) {
    return `₱${value.toLocaleString('en-PH', { maximumFractionDigits: 0 })}`;
}

function formatDate(value: string) {
    return new Date(value).toLocaleDateString('en-PH', { dateStyle: 'medium' });
}

function formatDateTime(value: string) {
    return new Date(value).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
}

// Restock History filters — client-side, since the list is a fixed
// 20-record window already loaded with the page.
const restockSearch = ref('');
const restockPricedOnly = ref(false);

const filteredRestockHistory = computed(() => {
    return props.restockHistory.filter((log) => {
        const matchesSearch = restockSearch.value
            ? log.ingredient_name.toLowerCase().includes(restockSearch.value.toLowerCase())
            : true;
        const matchesPriced = restockPricedOnly.value ? log.price !== null : true;
        return matchesSearch && matchesPriced;
    });
});

// Product Restock History filters — same client-side approach. "Increases
// only" hides adjustments/corrections that removed stock (negative delta),
// showing just actual restocks.
const productRestockSearch = ref('');
const productRestockIncreasesOnly = ref(false);

const filteredProductRestockHistory = computed(() => {
    return props.productRestockHistory.filter((log) => {
        const matchesSearch = productRestockSearch.value
            ? log.product_name.toLowerCase().includes(productRestockSearch.value.toLowerCase())
            : true;
        const matchesIncrease = productRestockIncreasesOnly.value ? log.quantity_change > 0 : true;
        return matchesSearch && matchesIncrease;
    });
});

// Which summary card is currently selected. 'total' has no drill-down
// table, so selecting it just highlights the card and hides the panel below.
type SectionKey = 'total' | 'value' | 'low_stock' | 'expiring' | 'out_of_stock';
const activeSection = ref<SectionKey>('low_stock');

function selectSection(key: SectionKey) {
    activeSection.value = key;
}

// Refs for the two <details> history panels, so Print can force them open —
// a collapsed <details> is skipped by the browser's print renderer.
const restockDetails = ref<HTMLDetailsElement | null>(null);
const productRestockDetails = ref<HTMLDetailsElement | null>(null);

function printReport() {
    if (restockDetails.value) restockDetails.value.open = true;
    if (productRestockDetails.value) productRestockDetails.value.open = true;
    window.print();
}
</script>

<template>
    <Head title="Inventory Report" />

    <AppLayout>
        <div class="space-y-4 p-3 sm:p-4">
            <div class="flex flex-col gap-4 print:hidden sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Inventory Report</h1>
                    <p class="text-sm text-muted-foreground">Current stock levels and expiry monitoring</p>
                </div>

                <button
                    @click="printReport"
                    class="flex items-center gap-2 rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:opacity-90"
                >
                    <Printer class="h-4 w-4" />
                    Print
                </button>
            </div>

            <div class="hidden print:block text-center mb-4">
                <h1 class="text-xl font-bold">Inventory Report</h1>
                <p class="text-sm">{{ new Date().toLocaleDateString('en-PH', { dateStyle: 'long' }) }}</p>
            </div>

            <!-- Summary Cards (now clickable tabs) -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5 print:hidden">
                <button
                    type="button"
                    @click="selectSection('total')"
                    class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                    :class="activeSection === 'total' ? 'border-primary ring-1 ring-primary' : ''"
                >
                    <p class="text-xs text-muted-foreground">Total Ingredients</p>
                    <p class="text-xl font-semibold">{{ summary.total_ingredients }}</p>
                </button>

                <button
                    type="button"
                    @click="selectSection('value')"
                    class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                    :class="activeSection === 'value' ? 'border-primary ring-1 ring-primary' : ''"
                >
                    <p class="text-xs text-muted-foreground">Total Stock Value</p>
                    <p class="text-xl font-semibold">{{ formatCurrency(summary.total_stock_value) }}</p>
                    <p v-if="summary.total_stock_value === null" class="text-[10px] text-muted-foreground mt-1">
                        Cost tracking not set up yet
                    </p>
                </button>

                <button
                    type="button"
                    @click="selectSection('low_stock')"
                    class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                    :class="activeSection === 'low_stock' ? 'border-primary ring-1 ring-primary' : ''"
                >
                    <p class="text-xs text-muted-foreground">Low Stock Items</p>
                    <p class="text-xl font-semibold" :class="summary.low_stock_count ? 'text-amber-600' : ''">
                        {{ summary.low_stock_count }}
                    </p>
                </button>

                <button
                    type="button"
                    @click="selectSection('expiring')"
                    class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                    :class="activeSection === 'expiring' ? 'border-primary ring-1 ring-primary' : ''"
                >
                    <p class="text-xs text-muted-foreground">Expiring Soon</p>
                    <p class="text-xl font-semibold" :class="summary.expiring_soon_count ? 'text-amber-600' : ''">
                        {{ summary.expiring_soon_count }}
                    </p>
                </button>

                <button
                    type="button"
                    @click="selectSection('out_of_stock')"
                    class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                    :class="activeSection === 'out_of_stock' ? 'border-primary ring-1 ring-primary' : ''"
                >
                    <p class="text-xs text-muted-foreground">Out of Stock</p>
                    <p class="text-xl font-semibold" :class="summary.out_of_stock_count ? 'text-destructive' : ''">
                        {{ summary.out_of_stock_count }}
                    </p>
                </button>
            </div>

            <!-- Plain (non-interactive) summary grid for print, so all stats show regardless of the selected tab -->
            <div class="hidden print:grid grid-cols-5 gap-4">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Total Ingredients</p>
                    <p class="text-xl font-semibold">{{ summary.total_ingredients }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Total Stock Value</p>
                    <p class="text-xl font-semibold">{{ formatCurrency(summary.total_stock_value) }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Low Stock Items</p>
                    <p class="text-xl font-semibold">{{ summary.low_stock_count }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Expiring Soon</p>
                    <p class="text-xl font-semibold">{{ summary.expiring_soon_count }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Out of Stock</p>
                    <p class="text-xl font-semibold">{{ summary.out_of_stock_count }}</p>
                </div>
            </div>

            <!-- Total Stock Value panel -->
            <div
                class="overflow-x-auto rounded-lg border"
                :class="activeSection === 'value' ? 'block' : 'hidden print:block'"
            >
                <div class="flex items-center justify-between p-4">
                    <h2 class="font-semibold">Total Stock Value</h2>
                    <span
                        v-if="stockValueItems.length"
                        class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                    >
                        {{ stockValueItems.length }}
                    </span>
                </div>
                <table class="w-full min-w-[520px] border-t text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">Ingredient</th>
                            <th class="p-2 text-right">Current Stock</th>
                            <th class="p-2 text-right">Unit Cost</th>
                            <th class="p-2 text-right">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in stockValueItems" :key="item.id" class="border-t">
                            <td class="p-2">{{ item.name }}</td>
                            <td class="p-2 text-right">{{ item.total_stock }} {{ item.unit }}</td>
                            <td class="p-2 text-right">{{ formatWholePeso(item.unit_cost) }}</td>
                            <td class="p-2 text-right">{{ formatCurrency(item.total_value) }}</td>
                        </tr>
                        <tr v-if="!stockValueItems.length">
                            <td colspan="4" class="p-4 text-center text-muted-foreground">
                                No ingredients have a cost set yet. Add one from the ingredient's Edit dialog.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="stockValueItems.length" class="border-t bg-muted/50">
                        <tr>
                            <td colspan="3" class="p-2 text-right font-semibold">Total</td>
                            <td class="p-2 text-right font-semibold">{{ formatCurrency(summary.total_stock_value) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Low Stock Items panel -->
            <div
                class="overflow-x-auto rounded-lg border"
                :class="activeSection === 'low_stock' ? 'block' : 'hidden print:block'"
            >
                <div class="flex items-center justify-between p-4">
                    <h2 class="font-semibold">Low Stock Items</h2>
                    <span
                        v-if="lowStockItems.length"
                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700"
                    >
                        {{ lowStockItems.length }}
                    </span>
                </div>
                <table class="w-full min-w-[460px] border-t text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">Ingredient</th>
                            <th class="p-2 text-right">Current Stock</th>
                            <th class="p-2 text-right">Minimum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in lowStockItems" :key="item.id" class="border-t">
                            <td class="p-2">{{ item.name }}</td>
                            <td class="p-2 text-right">{{ item.total_stock }} {{ item.unit }}</td>
                            <td class="p-2 text-right">{{ item.minimum_stock }} {{ item.unit }}</td>
                        </tr>
                        <tr v-if="!lowStockItems.length">
                            <td colspan="3" class="p-4 text-center text-muted-foreground">No low stock items</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Expiring Soon panel -->
            <div
                class="overflow-x-auto rounded-lg border"
                :class="activeSection === 'expiring' ? 'block' : 'hidden print:block'"
            >
                <div class="flex items-center justify-between p-4">
                    <h2 class="font-semibold">Expiring Soon</h2>
                    <span
                        v-if="expiringSoon.length"
                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700"
                    >
                        {{ expiringSoon.length }}
                    </span>
                </div>
                <table class="w-full min-w-[460px] border-t text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">Ingredient</th>
                            <th class="p-2 text-right">Remaining</th>
                            <th class="p-2 text-right">Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="batch in expiringSoon" :key="batch.id" class="border-t">
                            <td class="p-2">{{ batch.ingredient_name }}</td>
                            <td class="p-2 text-right">{{ batch.remaining_quantity }} {{ batch.unit }}</td>
                            <td class="p-2 text-right">{{ formatDate(batch.expiry_date) }}</td>
                        </tr>
                        <tr v-if="!expiringSoon.length">
                            <td colspan="3" class="p-4 text-center text-muted-foreground">No batches expiring soon</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Out of Stock panel -->
            <div
                class="overflow-x-auto rounded-lg border"
                :class="activeSection === 'out_of_stock' ? 'block' : 'hidden print:block'"
            >
                <div class="flex items-center justify-between p-4">
                    <h2 class="font-semibold">Out of Stock</h2>
                    <span
                        v-if="outOfStockItems.length"
                        class="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive"
                    >
                        {{ outOfStockItems.length }}
                    </span>
                </div>
                <table class="w-full min-w-[400px] border-t text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">Ingredient</th>
                            <th class="p-2 text-left">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in outOfStockItems" :key="item.id" class="border-t">
                            <td class="p-2">{{ item.name }}</td>
                            <td class="p-2">{{ item.unit }}</td>
                        </tr>
                        <tr v-if="!outOfStockItems.length">
                            <td colspan="2" class="p-4 text-center text-muted-foreground">Nothing out of stock</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Recent Restock History (ingredients) -->
            <details ref="restockDetails" class="group overflow-x-auto rounded-lg border">
                <summary class="flex cursor-pointer list-none items-center justify-between p-4 select-none">
                    <div>
                        <h2 class="font-semibold">Recent Restock History</h2>
                        <p class="text-xs text-muted-foreground">Last 20 ingredient batches received</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            v-if="restockHistory.length"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                        >
                            {{ filteredRestockHistory.length }}/{{ restockHistory.length }}
                        </span>
                        <ChevronDown class="h-4 w-4 transition-transform group-open:rotate-180" />
                    </div>
                </summary>

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3 border-t p-3 print:hidden">
                    <input
                        v-model="restockSearch"
                        type="text"
                        placeholder="Search ingredient..."
                        class="h-8 w-full max-w-xs rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <label class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <input v-model="restockPricedOnly" type="checkbox" class="h-3.5 w-3.5" />
                        Priced only
                    </label>
                </div>

                <table class="w-full min-w-[760px] border-t text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">Date Logged</th>
                            <th class="p-2 text-left">Ingredient</th>
                            <th class="p-2 text-right">Quantity Added</th>
                            <th class="p-2 text-right">Batch Expiry</th>
                            <th class="p-2 text-right">Price</th>
                            <th class="p-2 text-left">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in filteredRestockHistory" :key="log.id" class="border-t">
                            <td class="p-2">{{ formatDateTime(log.created_at) }}</td>
                            <td class="p-2">{{ log.ingredient_name }}</td>
                            <td class="p-2 text-right">+{{ log.quantity_change }} {{ log.unit }}</td>
                            <td class="p-2 text-right">{{ log.expiry_date ? formatDate(log.expiry_date) : '—' }}</td>
                            <td class="p-2 text-right">{{ log.price !== null ? formatWholePeso(log.price) : '—' }}</td>
                            <td class="p-2">{{ log.note ?? '—' }}</td>
                        </tr>
                        <tr v-if="!filteredRestockHistory.length">
                            <td colspan="6" class="p-4 text-center text-muted-foreground">
                                {{ restockHistory.length ? 'No restocks match your filters' : 'No restock history yet' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </details>

            <!-- Recent Product Restock History (finished_stock products, e.g. cookies) -->
            <details ref="productRestockDetails" class="group overflow-x-auto rounded-lg border">
                <summary class="flex cursor-pointer list-none items-center justify-between p-4 select-none">
                    <div>
                        <h2 class="font-semibold">Recent Product Restock History</h2>
                        <p class="text-xs text-muted-foreground">Last 20 finished-stock adjustments (cookies, etc.)</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            v-if="productRestockHistory.length"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                        >
                            {{ filteredProductRestockHistory.length }}/{{ productRestockHistory.length }}
                        </span>
                        <ChevronDown class="h-4 w-4 transition-transform group-open:rotate-180" />
                    </div>
                </summary>

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3 border-t p-3 print:hidden">
                    <input
                        v-model="productRestockSearch"
                        type="text"
                        placeholder="Search product..."
                        class="h-8 w-full max-w-xs rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <label class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <input v-model="productRestockIncreasesOnly" type="checkbox" class="h-3.5 w-3.5" />
                        Increases only
                    </label>
                </div>

                <table class="w-full min-w-[640px] border-t text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">Date Logged</th>
                            <th class="p-2 text-left">Product</th>
                            <th class="p-2 text-left">Type</th>
                            <th class="p-2 text-right">Quantity Change</th>
                            <th class="p-2 text-left">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in filteredProductRestockHistory" :key="log.id" class="border-t">
                            <td class="p-2">{{ formatDateTime(log.created_at) }}</td>
                            <td class="p-2">{{ log.product_name }}</td>
                            <td class="p-2 capitalize">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        log.type === 'restock'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                    "
                                >
                                    {{ log.type }}
                                </span>
                            </td>
                            <td
                                class="p-2 text-right font-medium"
                                :class="log.quantity_change > 0 ? 'text-green-700' : 'text-destructive'"
                            >
                                {{ log.quantity_change > 0 ? '+' : '' }}{{ log.quantity_change }} pcs
                            </td>
                            <td class="p-2">{{ log.note ?? '—' }}</td>
                        </tr>
                        <tr v-if="!filteredProductRestockHistory.length">
                            <td colspan="5" class="p-4 text-center text-muted-foreground">
                                {{ productRestockHistory.length ? 'No entries match your filters' : 'No product restock history yet' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </details>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    @page {
        margin: 1cm;
    }
    body * {
        visibility: visible;
    }
    details summary {
        cursor: default;
    }
    details summary svg {
        display: none;
    }
}
</style>