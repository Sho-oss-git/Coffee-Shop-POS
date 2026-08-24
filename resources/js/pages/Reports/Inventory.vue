<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ChevronDown, Download, Printer } from 'lucide-vue-next';
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

    return `₱${value.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

// unit_cost is a whole-peso amount.
function formatWholePeso(value: number) {
    return `₱${value.toLocaleString('en-PH', {
        maximumFractionDigits: 0,
    })}`;
}

function formatDate(value: string) {
    return new Date(value).toLocaleDateString('en-PH', {
        dateStyle: 'medium',
    });
}

function formatDateTime(value: string) {
    return new Date(value).toLocaleString('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

// ============================================================
// RESTOCK HISTORY FILTERS
// ============================================================

const restockSearch = ref('');
const restockPricedOnly = ref(false);

const filteredRestockHistory = computed(() => {
    return props.restockHistory.filter((log) => {
        const matchesSearch = restockSearch.value
            ? log.ingredient_name
                  .toLowerCase()
                  .includes(restockSearch.value.toLowerCase())
            : true;

        const matchesPriced = restockPricedOnly.value
            ? log.price !== null
            : true;

        return matchesSearch && matchesPriced;
    });
});

// ============================================================
// PRODUCT RESTOCK HISTORY FILTERS
// ============================================================

const productRestockSearch = ref('');
const productRestockIncreasesOnly = ref(false);

const filteredProductRestockHistory = computed(() => {
    return props.productRestockHistory.filter((log) => {
        const matchesSearch = productRestockSearch.value
            ? log.product_name
                  .toLowerCase()
                  .includes(productRestockSearch.value.toLowerCase())
            : true;

        const matchesIncrease = productRestockIncreasesOnly.value
            ? log.quantity_change > 0
            : true;

        return matchesSearch && matchesIncrease;
    });
});

// ============================================================
// SUMMARY SECTION
// ============================================================

type SectionKey =
    | 'total'
    | 'value'
    | 'low_stock'
    | 'expiring'
    | 'out_of_stock';

const activeSection = ref<SectionKey>('low_stock');

function selectSection(key: SectionKey) {
    activeSection.value = key;
}

// ============================================================
// EXCEL EXPORT DROPDOWN
// ============================================================

const exportMenuOpen = ref(false);

// ============================================================
// WORD PRINT DROPDOWN
// ============================================================

const printMenuOpen = ref(false);

// ============================================================
// REPORT SECTIONS
// ============================================================

const exportSheets = [
    {
        key: 'summary',
        label: 'Inventory Summary',
    },
    {
        key: 'stock-in',
        label: 'Stock-In / Restocking',
    },
    {
        key: 'movement',
        label: 'Inventory Movement',
    },
    {
        key: 'batch-expiry',
        label: 'Batch & Expiry',
    },
    {
        key: 'low-stock',
        label: 'Low Stock Report',
    },
];
</script>

<template>
    <Head title="Inventory Report" />

    <AppLayout>
        <div class="space-y-4 p-3 sm:p-4">

            <!-- ======================================================
                 PAGE HEADER
            ======================================================= -->

            <div
                class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        Inventory Report
                    </h1>

                    <p class="text-sm text-muted-foreground">
                        Current stock levels and expiry monitoring
                    </p>
                </div>

                <!-- ==================================================
                     EXPORT BUTTONS
                =================================================== -->

                <div class="flex items-center gap-2">

                    <!-- ==================================================
                         EXCEL EXPORT DROPDOWN
                    =================================================== -->

                    <div class="relative">
                        <button
                            type="button"
                            @click="exportMenuOpen = !exportMenuOpen"
                            class="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                        >
                            <Download class="h-4 w-4" />

                            Export Excel

                            <ChevronDown class="h-4 w-4" />
                        </button>

                        <div
                            v-if="exportMenuOpen"
                            class="absolute right-0 z-10 mt-1 w-56 rounded-md border bg-background shadow-lg"
                            @click="exportMenuOpen = false"
                        >
                            <!-- Full Excel Report -->

                            <a
                                :href="
                                    route(
                                        'reports.inventory.export'
                                    )
                                "
                                class="block border-b px-3 py-2 text-sm font-medium hover:bg-muted"
                            >
                                Full Report (all sheets)
                            </a>

                            <!-- Individual Excel Sheets -->

                            <a
                                v-for="sheet in exportSheets"
                                :key="sheet.key"
                                :href="
                                    route(
                                        'reports.inventory.export.sheet',
                                        sheet.key
                                    )
                                "
                                class="block px-3 py-2 text-sm hover:bg-muted"
                            >
                                {{ sheet.label }} only
                            </a>
                        </div>
                    </div>

                    <!-- ==================================================
                         WORD / PRINT DROPDOWN
                    =================================================== -->

                    <div class="relative">
                        <button
                            type="button"
                            @click="printMenuOpen = !printMenuOpen"
                            class="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                        >
                            <Printer class="h-4 w-4" />

                            Print (Word)

                            <ChevronDown class="h-4 w-4" />
                        </button>

                        <div
                            v-if="printMenuOpen"
                            class="absolute right-0 z-10 mt-1 w-56 rounded-md border bg-background shadow-lg"
                            @click="printMenuOpen = false"
                        >
                            <!-- Full Word Report -->

                            <a
                                :href="
                                    route(
                                        'reports.inventory.export.word'
                                    )
                                "
                                class="block border-b px-3 py-2 text-sm font-medium hover:bg-muted"
                            >
                                Full Report (all sections)
                            </a>

                            <!-- Individual Word Sections -->

                            <a
                                v-for="sheet in exportSheets"
                                :key="sheet.key"
                                :href="
                                    route(
                                        'reports.inventory.export.word.sheet',
                                        sheet.key
                                    )
                                "
                                class="block px-3 py-2 text-sm hover:bg-muted"
                            >
                                {{ sheet.label }} only
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================
                 SUMMARY CARDS
            ======================================================= -->

            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">

                <!-- Total Ingredients -->

                <button
                    type="button"
                    @click="selectSection('total')"
                    class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                    :class="
                        activeSection === 'total'
                            ? 'border-primary ring-1 ring-primary'
                            : ''
                    "
                >
                    <p class="text-xs text-muted-foreground">
                        Total Ingredients
                    </p>

                    <p class="text-xl font-semibold">
                        {{ summary.total_ingredients }}
                    </p>
                </button>

                <!-- Total Stock Value -->

                <button
                    type="button"
                    @click="selectSection('value')"
                    class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                    :class="
                        activeSection === 'value'
                            ? 'border-primary ring-1 ring-primary'
                            : ''
                    "
                >
                    <p class="text-xs text-muted-foreground">
                        Total Stock Value
                    </p>

                    <p class="text-xl font-semibold">
                        {{
                            formatCurrency(
                                summary.total_stock_value
                            )
                        }}
                    </p>

                    <p
                        v-if="summary.total_stock_value === null"
                        class="mt-1 text-[10px] text-muted-foreground"
                    >
                        Cost tracking not set up yet
                    </p>
                </button>

                <!-- Low Stock -->

                <button
                    type="button"
                    @click="selectSection('low_stock')"
                    class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                    :class="
                        activeSection === 'low_stock'
                            ? 'border-primary ring-1 ring-primary'
                            : ''
                    "
                >
                    <p class="text-xs text-muted-foreground">
                        Low Stock Items
                    </p>

                    <p
                        class="text-xl font-semibold"
                        :class="
                            summary.low_stock_count
                                ? 'text-amber-600'
                                : ''
                        "
                    >
                        {{ summary.low_stock_count }}
                    </p>
                </button>

                <!-- Expiring -->

                <button
                    type="button"
                    @click="selectSection('expiring')"
                    class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                    :class="
                        activeSection === 'expiring'
                            ? 'border-primary ring-1 ring-primary'
                            : ''
                    "
                >
                    <p class="text-xs text-muted-foreground">
                        Expiring Soon
                    </p>

                    <p
                        class="text-xl font-semibold"
                        :class="
                            summary.expiring_soon_count
                                ? 'text-amber-600'
                                : ''
                        "
                    >
                        {{ summary.expiring_soon_count }}
                    </p>
                </button>

                <!-- Out Of Stock -->

                <button
                    type="button"
                    @click="selectSection('out_of_stock')"
                    class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                    :class="
                        activeSection === 'out_of_stock'
                            ? 'border-primary ring-1 ring-primary'
                            : ''
                    "
                >
                    <p class="text-xs text-muted-foreground">
                        Out of Stock
                    </p>

                    <p
                        class="text-xl font-semibold"
                        :class="
                            summary.out_of_stock_count
                                ? 'text-destructive'
                                : ''
                        "
                    >
                        {{ summary.out_of_stock_count }}
                    </p>
                </button>
            </div>

            <!-- ======================================================
                 TOTAL STOCK VALUE
            ======================================================= -->

            <div
                class="overflow-x-auto rounded-lg border"
                :class="
                    activeSection === 'value'
                        ? 'block'
                        : 'hidden'
                "
            >
                <div class="flex items-center justify-between p-4">
                    <h2 class="font-semibold">
                        Total Stock Value
                    </h2>

                    <span
                        v-if="stockValueItems.length"
                        class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                    >
                        {{ stockValueItems.length }}
                    </span>
                </div>

                <table
                    class="w-full min-w-[520px] border-t text-sm"
                >
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">
                                Ingredient
                            </th>

                            <th class="p-2 text-right">
                                Current Stock
                            </th>

                            <th class="p-2 text-right">
                                Unit Cost
                            </th>

                            <th class="p-2 text-right">
                                Total Value
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="item in stockValueItems"
                            :key="item.id"
                            class="border-t"
                        >
                            <td class="p-2">
                                {{ item.name }}
                            </td>

                            <td class="p-2 text-right">
                                {{ item.total_stock }}
                                {{ item.unit }}
                            </td>

                            <td class="p-2 text-right">
                                {{ formatWholePeso(item.unit_cost) }}
                            </td>

                            <td class="p-2 text-right">
                                {{ formatCurrency(item.total_value) }}
                            </td>
                        </tr>

                        <tr v-if="!stockValueItems.length">
                            <td
                                colspan="4"
                                class="p-4 text-center text-muted-foreground"
                            >
                                No ingredients have a cost set yet.
                                Add one from the ingredient's Edit dialog.
                            </td>
                        </tr>
                    </tbody>

                    <tfoot
                        v-if="stockValueItems.length"
                        class="border-t bg-muted/50"
                    >
                        <tr>
                            <td
                                colspan="3"
                                class="p-2 text-right font-semibold"
                            >
                                Total
                            </td>

                            <td
                                class="p-2 text-right font-semibold"
                            >
                                {{
                                    formatCurrency(
                                        summary.total_stock_value
                                    )
                                }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- ======================================================
                 LOW STOCK
            ======================================================= -->

            <div
                class="overflow-x-auto rounded-lg border"
                :class="
                    activeSection === 'low_stock'
                        ? 'block'
                        : 'hidden'
                "
            >
                <div class="flex items-center justify-between p-4">
                    <h2 class="font-semibold">
                        Low Stock Items
                    </h2>

                    <span
                        v-if="lowStockItems.length"
                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700"
                    >
                        {{ lowStockItems.length }}
                    </span>
                </div>

                <table
                    class="w-full min-w-[460px] border-t text-sm"
                >
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">
                                Ingredient
                            </th>

                            <th class="p-2 text-right">
                                Current Stock
                            </th>

                            <th class="p-2 text-right">
                                Minimum
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="item in lowStockItems"
                            :key="item.id"
                            class="border-t"
                        >
                            <td class="p-2">
                                {{ item.name }}
                            </td>

                            <td class="p-2 text-right">
                                {{ item.total_stock }}
                                {{ item.unit }}
                            </td>

                            <td class="p-2 text-right">
                                {{ item.minimum_stock }}
                                {{ item.unit }}
                            </td>
                        </tr>

                        <tr v-if="!lowStockItems.length">
                            <td
                                colspan="3"
                                class="p-4 text-center text-muted-foreground"
                            >
                                No low stock items
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ======================================================
                 EXPIRING SOON
            ======================================================= -->

            <div
                class="overflow-x-auto rounded-lg border"
                :class="
                    activeSection === 'expiring'
                        ? 'block'
                        : 'hidden'
                "
            >
                <div class="flex items-center justify-between p-4">
                    <h2 class="font-semibold">
                        Expiring Soon
                    </h2>

                    <span
                        v-if="expiringSoon.length"
                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700"
                    >
                        {{ expiringSoon.length }}
                    </span>
                </div>

                <table
                    class="w-full min-w-[460px] border-t text-sm"
                >
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">
                                Ingredient
                            </th>

                            <th class="p-2 text-right">
                                Remaining
                            </th>

                            <th class="p-2 text-right">
                                Expiry Date
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="batch in expiringSoon"
                            :key="batch.id"
                            class="border-t"
                        >
                            <td class="p-2">
                                {{ batch.ingredient_name }}
                            </td>

                            <td class="p-2 text-right">
                                {{ batch.remaining_quantity }}
                                {{ batch.unit }}
                            </td>

                            <td class="p-2 text-right">
                                {{ formatDate(batch.expiry_date) }}
                            </td>
                        </tr>

                        <tr v-if="!expiringSoon.length">
                            <td
                                colspan="3"
                                class="p-4 text-center text-muted-foreground"
                            >
                                No batches expiring soon
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ======================================================
                 OUT OF STOCK
            ======================================================= -->

            <div
                class="overflow-x-auto rounded-lg border"
                :class="
                    activeSection === 'out_of_stock'
                        ? 'block'
                        : 'hidden'
                "
            >
                <div class="flex items-center justify-between p-4">
                    <h2 class="font-semibold">
                        Out of Stock
                    </h2>

                    <span
                        v-if="outOfStockItems.length"
                        class="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive"
                    >
                        {{ outOfStockItems.length }}
                    </span>
                </div>

                <table
                    class="w-full min-w-[400px] border-t text-sm"
                >
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">
                                Ingredient
                            </th>

                            <th class="p-2 text-left">
                                Unit
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="item in outOfStockItems"
                            :key="item.id"
                            class="border-t"
                        >
                            <td class="p-2">
                                {{ item.name }}
                            </td>

                            <td class="p-2">
                                {{ item.unit }}
                            </td>
                        </tr>

                        <tr v-if="!outOfStockItems.length">
                            <td
                                colspan="2"
                                class="p-4 text-center text-muted-foreground"
                            >
                                Nothing out of stock
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ======================================================
                 RECENT RESTOCK HISTORY
            ======================================================= -->

            <details
                class="group overflow-x-auto rounded-lg border"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between p-4 select-none"
                >
                    <div>
                        <h2 class="font-semibold">
                            Recent Restock History
                        </h2>

                        <p class="text-xs text-muted-foreground">
                            Last 20 ingredient batches received
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            v-if="restockHistory.length"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                        >
                            {{ filteredRestockHistory.length }}/{{
                                restockHistory.length
                            }}
                        </span>

                        <ChevronDown
                            class="h-4 w-4 transition-transform group-open:rotate-180"
                        />
                    </div>
                </summary>

                <div
                    class="flex flex-wrap items-center gap-3 border-t p-3"
                >
                    <input
                        v-model="restockSearch"
                        type="text"
                        placeholder="Search ingredient..."
                        class="h-8 w-full max-w-xs rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    />

                    <label
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <input
                            v-model="restockPricedOnly"
                            type="checkbox"
                            class="h-3.5 w-3.5"
                        />

                        Priced only
                    </label>
                </div>

                <table
                    class="w-full min-w-[760px] border-t text-sm"
                >
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">
                                Date Logged
                            </th>

                            <th class="p-2 text-left">
                                Ingredient
                            </th>

                            <th class="p-2 text-right">
                                Quantity Added
                            </th>

                            <th class="p-2 text-right">
                                Batch Expiry
                            </th>

                            <th class="p-2 text-right">
                                Price
                            </th>

                            <th class="p-2 text-left">
                                Note
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="log in filteredRestockHistory"
                            :key="log.id"
                            class="border-t"
                        >
                            <td class="p-2">
                                {{ formatDateTime(log.created_at) }}
                            </td>

                            <td class="p-2">
                                {{ log.ingredient_name }}
                            </td>

                            <td class="p-2 text-right">
                                +{{ log.quantity_change }}
                                {{ log.unit }}
                            </td>

                            <td class="p-2 text-right">
                                {{
                                    log.expiry_date
                                        ? formatDate(log.expiry_date)
                                        : '—'
                                }}
                            </td>

                            <td class="p-2 text-right">
                                {{
                                    log.price !== null
                                        ? formatWholePeso(log.price)
                                        : '—'
                                }}
                            </td>

                            <td class="p-2">
                                {{ log.note ?? '—' }}
                            </td>
                        </tr>

                        <tr
                            v-if="
                                !filteredRestockHistory.length
                            "
                        >
                            <td
                                colspan="6"
                                class="p-4 text-center text-muted-foreground"
                            >
                                {{
                                    restockHistory.length
                                        ? 'No restocks match your filters'
                                        : 'No restock history yet'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </details>

            <!-- ======================================================
                 PRODUCT RESTOCK HISTORY
            ======================================================= -->

            <details
                class="group overflow-x-auto rounded-lg border"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between p-4 select-none"
                >
                    <div>
                        <h2 class="font-semibold">
                            Recent Product Restock History
                        </h2>

                        <p class="text-xs text-muted-foreground">
                            Last 20 finished-stock adjustments
                            (cookies, etc.)
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            v-if="productRestockHistory.length"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                        >
                            {{
                                filteredProductRestockHistory.length
                            }}/{{ productRestockHistory.length }}
                        </span>

                        <ChevronDown
                            class="h-4 w-4 transition-transform group-open:rotate-180"
                        />
                    </div>
                </summary>

                <div
                    class="flex flex-wrap items-center gap-3 border-t p-3"
                >
                    <input
                        v-model="productRestockSearch"
                        type="text"
                        placeholder="Search product..."
                        class="h-8 w-full max-w-xs rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    />

                    <label
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <input
                            v-model="productRestockIncreasesOnly"
                            type="checkbox"
                            class="h-3.5 w-3.5"
                        />

                        Increases only
                    </label>
                </div>

                <table
                    class="w-full min-w-[640px] border-t text-sm"
                >
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">
                                Date Logged
                            </th>

                            <th class="p-2 text-left">
                                Product
                            </th>

                            <th class="p-2 text-left">
                                Type
                            </th>

                            <th class="p-2 text-right">
                                Quantity Change
                            </th>

                            <th class="p-2 text-left">
                                Note
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="log in filteredProductRestockHistory"
                            :key="log.id"
                            class="border-t"
                        >
                            <td class="p-2">
                                {{ formatDateTime(log.created_at) }}
                            </td>

                            <td class="p-2">
                                {{ log.product_name }}
                            </td>

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
                                :class="
                                    log.quantity_change > 0
                                        ? 'text-green-700'
                                        : 'text-destructive'
                                "
                            >
                                {{
                                    log.quantity_change > 0
                                        ? '+'
                                        : ''
                                }}{{ log.quantity_change }} pcs
                            </td>

                            <td class="p-2">
                                {{ log.note ?? '—' }}
                            </td>
                        </tr>

                        <tr
                            v-if="
                                !filteredProductRestockHistory.length
                            "
                        >
                            <td
                                colspan="5"
                                class="p-4 text-center text-muted-foreground"
                            >
                                {{
                                    productRestockHistory.length
                                        ? 'No entries match your filters'
                                        : 'No product restock history yet'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </details>
        </div>
    </AppLayout>
</template>