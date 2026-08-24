<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Printer, FileSpreadsheet, FileText } from 'lucide-vue-next';

interface Summary {
    total_sales: number;
    transaction_count: number;
    items_sold: number;
    average_sale: number;
    total_cogs: number;
    gross_profit: number;
    gross_margin: number;
}

interface BestSeller {
    name: string;
    quantity: number;
    total: number;
    cogs: number;
    gross_profit: number;
    margin: number;
    has_incomplete_cost: boolean;
}

interface PaymentMethodSummary {
    method: string;
    label: string;
    transactions: number;
    total_sales: number;
    percentage: number;
}

interface OrderTypeSummary {
    order_type: string;
    label: string;
    transactions: number;
    items_sold: number;
    total_sales: number;
}

const props = defineProps<{
    summary: Summary;
    bestSellers: BestSeller[];
    salesByPaymentMethod: PaymentMethodSummary[];
    orderTypeSummary: OrderTypeSummary[];
    filters: { period: 'daily' | 'monthly' | 'yearly'; date: string };
}>();

const period = ref(props.filters.period);
const date = ref(props.filters.date);

const periodLabel = computed(() => {
    if (period.value === 'daily') return 'Daily';
    if (period.value === 'monthly') return 'Monthly';
    return 'Yearly';
});

const hasIncompleteCost = computed(() => props.bestSellers.some((b) => b.has_incomplete_cost));

const paymentTotals = computed(() => ({
    transactions: props.salesByPaymentMethod.reduce((sum, p) => sum + p.transactions, 0),
    total_sales: props.salesByPaymentMethod.reduce((sum, p) => sum + p.total_sales, 0),
}));

const orderTypeTotals = computed(() => ({
    transactions: props.orderTypeSummary.reduce((sum, o) => sum + o.transactions, 0),
    items_sold: props.orderTypeSummary.reduce((sum, o) => sum + o.items_sold, 0),
    total_sales: props.orderTypeSummary.reduce((sum, o) => sum + o.total_sales, 0),
}));

function applyFilters() {
    router.get(
        route('reports.sales'),
        { period: period.value, date: date.value },
        { preserveState: true, preserveScroll: true },
    );
}

function setPeriod(p: 'daily' | 'monthly' | 'yearly') {
    period.value = p;
    applyFilters();
}

function formatCurrency(value: number | string) {
    return `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatPercent(value: number | string) {
    return `${Number(value).toFixed(2)}%`;
}

function printReport() {
    window.print();
}

function exportUrl(kind: 'excel' | 'word') {
    const routeName = kind === 'excel' ? 'reports.sales.export' : 'reports.sales.export.word';
    return route(routeName, { period: period.value, date: date.value });
}

function exportExcel() {
    window.location.href = exportUrl('excel');
}

function exportWord() {
    window.location.href = exportUrl('word');
}
</script>

<template>
    <Head title="Sales Report" />

    <AppLayout>
        <div class="space-y-6 p-3 sm:p-4 print:p-0">
            <div class="flex flex-col gap-4 print:hidden sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Sales Report</h1>
                    <p class="text-sm text-muted-foreground">Monitor sales by day, month, or year</p>
                </div>

                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
                    <div class="flex rounded-md border overflow-hidden">
                        <button
                            v-for="p in ['daily', 'monthly', 'yearly'] as const"
                            :key="p"
                            @click="setPeriod(p)"
                            class="px-3 py-1.5 text-sm capitalize"
                            :class="period === p ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted'"
                        >
                            {{ p }}
                        </button>
                    </div>

                    <input
                        v-model="date"
                        :type="period === 'yearly' ? 'number' : period === 'monthly' ? 'month' : 'date'"
                        @change="applyFilters"
                        class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground dark:[color-scheme:dark] sm:w-auto"
                    />

                    <button
                        @click="printReport"
                        class="flex items-center gap-2 rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:opacity-90"
                    >
                        <Printer class="h-4 w-4" />
                        Print
                    </button>

                    <button
                        @click="exportExcel"
                        class="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                    >
                        <FileSpreadsheet class="h-4 w-4" />
                        Export Excel
                    </button>

                    <button
                        @click="exportWord"
                        class="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                    >
                        <FileText class="h-4 w-4" />
                        Export Word
                    </button>
                </div>
            </div>

            <div class="hidden print:block text-center mb-4">
                <h1 class="text-xl font-bold">JC66 COFFEE SHOP</h1>
                <h2 class="text-lg font-semibold">SALES REPORT</h2>
                <p class="text-sm">{{ periodLabel }} — {{ date }}</p>
                <p class="text-xs text-muted-foreground">Generated: {{ new Date().toLocaleString('en-PH') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Total Sales</p>
                    <p class="text-xl font-semibold">{{ formatCurrency(summary.total_sales) }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Transactions</p>
                    <p class="text-xl font-semibold">{{ summary.transaction_count }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Items Sold</p>
                    <p class="text-xl font-semibold">{{ summary.items_sold }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Average Sale</p>
                    <p class="text-xl font-semibold">{{ formatCurrency(summary.average_sale) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Total COGS</p>
                    <p class="text-xl font-semibold">{{ formatCurrency(summary.total_cogs) }}</p>
                </div>
                <div class="rounded-lg border p-4 border-green-200 bg-green-50/50">
                    <p class="text-xs text-muted-foreground">Gross Profit</p>
                    <p class="text-xl font-semibold text-green-700">{{ formatCurrency(summary.gross_profit) }}</p>
                    <p v-if="hasIncompleteCost" class="mt-1 text-[11px] text-amber-600 print:hidden">
                        Some items are missing ingredient cost data — figure may be understated.
                    </p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Gross Margin</p>
                    <p class="text-xl font-semibold">{{ formatPercent(summary.gross_margin) }}</p>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="overflow-x-auto rounded-lg border">
                <div class="border-b p-4">
                    <h2 class="font-semibold">Payment Summary</h2>
                </div>
                <table class="w-full min-w-[480px] text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">Payment Method</th>
                            <th class="p-2 text-right">Transactions</th>
                            <th class="p-2 text-right">Total Sales</th>
                            <th class="p-2 text-right">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in salesByPaymentMethod" :key="p.method" class="border-t">
                            <td class="p-2">{{ p.label }}</td>
                            <td class="p-2 text-right">{{ p.transactions }}</td>
                            <td class="p-2 text-right">{{ formatCurrency(p.total_sales) }}</td>
                            <td class="p-2 text-right">{{ formatPercent(p.percentage) }}</td>
                        </tr>
                        <tr v-if="!salesByPaymentMethod.length">
                            <td colspan="4" class="p-4 text-center text-muted-foreground">No sales in this period</td>
                        </tr>
                        <tr v-else class="border-t bg-muted/30 font-medium">
                            <td class="p-2">Total</td>
                            <td class="p-2 text-right">{{ paymentTotals.transactions }}</td>
                            <td class="p-2 text-right">{{ formatCurrency(paymentTotals.total_sales) }}</td>
                            <td class="p-2 text-right">100.00%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Order Type Summary -->
            <div class="overflow-x-auto rounded-lg border">
                <div class="border-b p-4">
                    <h2 class="font-semibold">Order Type Summary</h2>
                </div>
                <table class="w-full min-w-[480px] text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">Order Type</th>
                            <th class="p-2 text-right">Transactions</th>
                            <th class="p-2 text-right">Items Sold</th>
                            <th class="p-2 text-right">Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in orderTypeSummary" :key="o.order_type" class="border-t">
                            <td class="p-2">{{ o.label }}</td>
                            <td class="p-2 text-right">{{ o.transactions }}</td>
                            <td class="p-2 text-right">{{ o.items_sold }}</td>
                            <td class="p-2 text-right">{{ formatCurrency(o.total_sales) }}</td>
                        </tr>
                        <tr v-if="!orderTypeSummary.length">
                            <td colspan="4" class="p-4 text-center text-muted-foreground">No sales in this period</td>
                        </tr>
                        <tr v-else class="border-t bg-muted/30 font-medium">
                            <td class="p-2">Total</td>
                            <td class="p-2 text-right">{{ orderTypeTotals.transactions }}</td>
                            <td class="p-2 text-right">{{ orderTypeTotals.items_sold }}</td>
                            <td class="p-2 text-right">{{ formatCurrency(orderTypeTotals.total_sales) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <div class="border-b p-4">
                    <h2 class="font-semibold">Best Selling Items</h2>
                </div>
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">Product</th>
                            <th class="p-2 text-right">Qty Sold</th>
                            <th class="p-2 text-right">Revenue</th>
                            <th class="p-2 text-right">COGS</th>
                            <th class="p-2 text-right">Gross Profit</th>
                            <th class="p-2 text-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in bestSellers" :key="item.name" class="border-t">
                            <td class="p-2">
                                {{ item.name }}
                                <span
                                    v-if="item.has_incomplete_cost"
                                    class="ml-1 text-[10px] text-amber-600"
                                    title="Cost data incomplete for this product — COGS may be understated"
                                >
                                    ⚠
                                </span>
                            </td>
                            <td class="p-2 text-right">{{ item.quantity }}</td>
                            <td class="p-2 text-right">{{ formatCurrency(item.total) }}</td>
                            <td class="p-2 text-right">{{ formatCurrency(item.cogs) }}</td>
                            <td class="p-2 text-right font-medium text-green-700">{{ formatCurrency(item.gross_profit) }}</td>
                            <td class="p-2 text-right">{{ formatPercent(item.margin) }}</td>
                        </tr>
                        <tr v-if="!bestSellers.length">
                            <td colspan="6" class="p-4 text-center text-muted-foreground">No sales in this period</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Print-only signature footer -->
            <div class="hidden print:grid print:grid-cols-3 print:gap-8 print:mt-10 print:text-sm">
                <div>
                    <p class="border-t border-black pt-1">Prepared by: __________________</p>
                </div>
                <div>
                    <p class="border-t border-black pt-1">Checked by: ___________________</p>
                </div>
                <div>
                    <p class="border-t border-black pt-1">Generated: ____________________</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    @page {
        margin: 1cm;
        size: landscape;
    }

    body {
        visibility: visible;
    }

    /*
     * Best-effort selectors for hiding the app chrome (sidebar/nav) that
     * AppLayout renders around this page. If your AppLayout.vue's sidebar
     * uses different markup than the common shadcn-vue `data-sidebar`
     * convention, adjust these selectors to match — everything on this
     * page itself is already scoped with print:hidden where needed.
     */
    [data-sidebar],
    aside,
    header nav {
        display: none !important;
    }
}
</style>