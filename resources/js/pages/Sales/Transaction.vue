<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Summary {
    total_sales: number;
    transaction_count: number;
    items_sold: number;
    average_sale: number;
    total_cogs: number;
    gross_profit: number;
}

interface RecentTransaction {
    id: number;
    time: string;
    cashier: string | null;
    total: number;
    status: string;
    payment_method: 'cash' | 'gcash' | null;
}

interface TopProduct {
    name: string;
    quantity: number;
}

interface CashierSales {
    cashier: string;
    transaction_count: number;
    sales: number;
}

interface PaymentMethodBreakdown {
    method: string;
    label: string;
    count: number;
    total: number;
}

const props = defineProps<{
    summary: Summary;
    recentTransactions: RecentTransaction[];
    topProducts: TopProduct[];
    salesByCashier: CashierSales[];
    salesByPaymentMethod: PaymentMethodBreakdown[];
    date: string;
}>();

function formatCurrency(value: number | string) {
    return `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDateLabel(value: string) {
    return new Date(value).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

function paymentIcon(method: string | null) {
    return method === 'gcash' ? '' : '';
}

function paymentLabel(method: string | null) {
    return method === 'gcash' ? 'GCash' : 'Cash';
}

const totalPaymentSales = computed(() => props.salesByPaymentMethod.reduce((sum, p) => sum + p.total, 0));

function paymentSharePercent(total: number) {
    return totalPaymentSales.value > 0 ? Math.round((total / totalPaymentSales.value) * 100) : 0;
}
</script>

<template>
    <Head title="Sale Transaction" />

    <AppLayout>
        <div class="min-w-0 space-y-6 p-3 sm:p-4">
            <div>
                <h1 class="text-2xl font-semibold">Sales Monitoring</h1>
                <p class="text-sm text-muted-foreground">{{ formatDateLabel(date) }} — live view of today's sales</p>
            </div>

            <!-- 1. Today's Sales Overview -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Today's Sales</p>
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
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">COGS</p>
                    <p class="text-xl font-semibold">{{ formatCurrency(summary.total_cogs) }}</p>
                </div>
                <div class="rounded-lg border p-4 border-green-200 bg-green-50/50">
                    <p class="text-xs text-muted-foreground">Gross Profit</p>
                    <p class="text-xl font-semibold text-green-700">{{ formatCurrency(summary.gross_profit) }}</p>
                </div>
            </div>

            <!-- 1b. Payment Methods (Cash vs GCash) -->
            <div class="rounded-lg border">
                <div class="border-b p-4">
                    <h2 class="font-semibold">Payment Methods</h2>
                </div>
                <div v-if="salesByPaymentMethod.length" class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                    <div
                        v-for="p in salesByPaymentMethod"
                        :key="p.method"
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">{{ paymentIcon(p.method) }}</span>
                            <div>
                                <p class="text-sm font-medium">{{ p.label }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ p.count }} {{ p.count === 1 ? 'sale' : 'sales' }} · {{ paymentSharePercent(p.total) }}% of today
                                </p>
                            </div>
                        </div>
                        <p class="text-lg font-semibold">{{ formatCurrency(p.total) }}</p>
                    </div>
                </div>
                <p v-else class="p-6 text-center text-sm text-muted-foreground">No sales yet today</p>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- 2. Live / Recent Transactions -->
                <div class="min-w-0 overflow-hidden rounded-lg border">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b p-4">
                        <h2 class="font-semibold">Recent Transactions</h2>
                        <Link :href="route('sales-history')" class="text-sm text-primary hover:underline">
                            View All →
                        </Link>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[620px] text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-2 text-left">Time</th>
                                <th class="p-2 text-left">Transaction</th>
                                <th class="p-2 text-left">Cashier</th>
                                <th class="p-2 text-left">Payment</th>
                                <th class="p-2 text-right">Total</th>
                                <th class="p-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in recentTransactions" :key="t.id" class="border-t">
                                <td class="p-2">{{ t.time }}</td>
                                <td class="p-2">#{{ t.id }}</td>
                                <td class="p-2">{{ t.cashier }}</td>
                                <td class="p-2">
                                    <span class="inline-flex items-center gap-1 text-xs">
                                        {{ paymentIcon(t.payment_method) }} {{ paymentLabel(t.payment_method) }}
                                    </span>
                                </td>
                                <td class="p-2 text-right">{{ formatCurrency(t.total) }}</td>
                                <td class="p-2 capitalize">{{ t.status }}</td>
                            </tr>
                            <tr v-if="!recentTransactions.length">
                                <td colspan="6" class="p-4 text-center text-muted-foreground">No transactions yet today</td>
                            </tr>
                        </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. Current Best Sellers -->
                <div class="rounded-lg border">
                    <div class="border-b p-4">
                        <h2 class="font-semibold">Today's Top Products</h2>
                    </div>
                    <ol class="divide-y">
                        <li
                            v-for="(product, index) in topProducts"
                            :key="product.name"
                            class="flex items-center justify-between p-3 text-sm"
                        >
                            <span>
                                <span class="text-muted-foreground mr-2">{{ index + 1 }}.</span>
                                {{ product.name }}
                            </span>
                            <span class="font-medium">{{ product.quantity }} sold</span>
                        </li>
                        <li v-if="!topProducts.length" class="p-4 text-center text-sm text-muted-foreground">
                            No sales yet today
                        </li>
                    </ol>
                </div>
            </div>

            <!-- 4. Sales by Cashier -->
            <div class="rounded-lg border">
                <div class="border-b p-4">
                    <h2 class="font-semibold">Sales by Cashier</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left">Cashier</th>
                            <th class="p-2 text-right">Transactions</th>
                            <th class="p-2 text-right">Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in salesByCashier" :key="c.cashier" class="border-t">
                            <td class="p-2">{{ c.cashier }}</td>
                            <td class="p-2 text-right">{{ c.transaction_count }}</td>
                            <td class="p-2 text-right">{{ formatCurrency(c.sales) }}</td>
                        </tr>
                        <tr v-if="!salesByCashier.length">
                            <td colspan="3" class="p-4 text-center text-muted-foreground">No sales yet today</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>