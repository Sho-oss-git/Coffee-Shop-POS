<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Receipt } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import TransactionReceipt from './TransactionReceipt.vue';

interface TransactionItem {
    product_name: string;
    quantity: number;
    price: number;
    subtotal: number;
}

interface HistoryTransaction {
    id: number;
    order_number: number;
    transaction_no: string;
    created_at: string;
    order_type: 'dine_in' | 'take_out';
    customer_name: string | null;
    notes: string | null;
    payment_method: 'cash' | 'gcash';
    status: 'completed' | 'refunded' | 'voided';
    total: number;
    amount_received: number;
    change: number;
    gcash_reference_number: string | null;
    items: TransactionItem[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTransactions {
    data: HistoryTransaction[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    transactions: PaginatedTransactions;
    filters: { status: 'all' | 'completed' | 'refunded' | 'voided' };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: route('cashier.products.index') },
    { title: 'Transaction History', href: route('cashier.transactions.history') },
];

const statusFilters: { value: 'all' | 'completed' | 'refunded' | 'voided'; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'completed', label: 'Completed' },
    { value: 'refunded', label: 'Refunded' },
    { value: 'voided', label: 'Voided' },
];

const isLoading = ref(false);
const expandedId = ref<number | null>(null);

function applyStatusFilter(status: string) {
    isLoading.value = true;
    router.get(
        route('cashier.transactions.history'),
        { status },
        { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (isLoading.value = false) },
    );
}

function goToPage(url: string | null) {
    if (!url) return;
    isLoading.value = true;
    router.get(url, {}, { preserveState: true, preserveScroll: true, onFinish: () => (isLoading.value = false) });
}

function toggleExpanded(id: number) {
    expandedId.value = expandedId.value === id ? null : id;
}

function statusClasses(status: string) {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-700';
        case 'voided':
            return 'bg-red-100 text-red-700';
        case 'refunded':
            return 'bg-amber-100 text-amber-700';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

function orderTypeLabel(type: string | null) {
    return type === 'dine_in' ? 'Dine-in' : type === 'take_out' ? 'Take-out' : '—';
}

function formatCurrency(value: number): string {
    return `₱${Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDateTime(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    return date.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

/* --- Receipt printing --- */
// Holds whichever transaction is about to be printed. The TransactionReceipt
// component is rendered hidden on screen (`hidden print:block`) and only
// becomes visible inside its own @media print rules, so window.print()
// prints just the receipt instead of the raw page.
const printingTransaction = ref<HistoryTransaction | null>(null);

async function handlePrintReceipt(transaction: HistoryTransaction) {
    printingTransaction.value = transaction;
    await nextTick();
    window.print();
}
</script>

<template>
    <Head title="Transaction History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-3 sm:p-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Transaction History</h1>
                    <p class="text-sm text-muted-foreground">Browse your past transactions</p>
                </div>

                <div class="flex w-full flex-wrap gap-2 sm:w-auto sm:justify-end">
                    <button
                        v-for="filter in statusFilters"
                        :key="filter.value"
                        type="button"
                        class="rounded-md border px-3 py-1.5 text-sm capitalize transition-colors"
                        :class="
                            props.filters.status === filter.value
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        "
                        @click="applyStatusFilter(filter.value)"
                    >
                        {{ filter.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <div class="flex items-center justify-between border-b p-4">
                    <h2 class="font-semibold">Transactions</h2>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="transactions.total">
                            Showing {{ transactions.from }}–{{ transactions.to }} of {{ transactions.total }}
                        </template>
                        <template v-else>No transactions found</template>
                    </p>
                </div>

                <div
                    v-if="transactions.data.length === 0"
                    class="flex min-h-[220px] flex-col items-center justify-center gap-3 p-6 text-center text-muted-foreground"
                >
                    <Receipt class="h-10 w-10" />
                    <p>No transactions found for this filter.</p>
                </div>

                <table v-else class="w-full min-w-[620px] text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="w-8 p-2"></th>
                            <th class="p-2 text-left">Transaction No.</th>
                            <th class="p-2 text-left">Date &amp; Time</th>
                            <th class="p-2 text-left">Payment</th>
                            <th class="p-2 text-right">Total</th>
                            <th class="p-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="t in transactions.data" :key="t.id">
                            <tr class="cursor-pointer border-t hover:bg-muted/30" @click="toggleExpanded(t.id)">
                                <td class="p-2 text-muted-foreground">
                                    <ChevronDown v-if="expandedId === t.id" class="h-4 w-4" />
                                    <ChevronRight v-else class="h-4 w-4" />
                                </td>
                                <td class="p-2 font-mono font-medium">{{ t.transaction_no }}</td>
                                <td class="p-2">{{ formatDateTime(t.created_at) }}</td>
                                <td class="p-2">{{ t.payment_method === 'gcash' ? 'GCash' : 'Cash' }}</td>
                                <td class="p-2 text-right font-medium">{{ formatCurrency(t.total) }}</td>
                                <td class="p-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClasses(t.status)">
                                        {{ t.status }}
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="expandedId === t.id" class="border-t bg-muted/20">
                                <td colspan="6" class="p-0">
                                    <div class="flex flex-col gap-4 p-4">
                                        <table class="w-full min-w-[420px] text-xs">
                                            <thead>
                                                <tr class="text-muted-foreground">
                                                    <th class="p-1.5 text-left">Product</th>
                                                    <th class="p-1.5 text-right">Price</th>
                                                    <th class="p-1.5 text-right">Qty</th>
                                                    <th class="p-1.5 text-right">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(item, idx) in t.items" :key="idx" class="border-t border-border/60">
                                                    <td class="p-1.5">{{ item.product_name }}</td>
                                                    <td class="p-1.5 text-right">{{ formatCurrency(item.price) }}</td>
                                                    <td class="p-1.5 text-right">{{ item.quantity }}</td>
                                                    <td class="p-1.5 text-right">{{ formatCurrency(item.subtotal) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted-foreground">
                                            <span v-if="t.order_number">
                                                Order: <span class="font-medium text-foreground">#{{ String(t.order_number).padStart(3, '0') }}</span>
                                            </span>
                                            <span v-if="t.order_type">
                                                Type: <span class="font-medium text-foreground">{{ orderTypeLabel(t.order_type) }}</span>
                                            </span>
                                            <span v-if="t.customer_name">
                                                Customer: <span class="font-medium text-foreground">{{ t.customer_name }}</span>
                                            </span>
                                            <span>
                                                Received: <span class="font-medium text-foreground">{{ formatCurrency(t.amount_received) }}</span>
                                            </span>
                                            <span>
                                                Change: <span class="font-medium text-foreground">{{ formatCurrency(t.change) }}</span>
                                            </span>
                                            <span v-if="t.payment_method === 'gcash' && t.gcash_reference_number">
                                                GCash Ref: <span class="font-medium text-foreground">{{ t.gcash_reference_number }}</span>
                                            </span>
                                            <span v-if="t.notes" class="w-full">
                                                Notes: <span class="font-medium text-foreground">{{ t.notes }}</span>
                                            </span>
                                        </div>

                                        <div>
                                            <button
                                                type="button"
                                                class="rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-muted"
                                                @click.stop="handlePrintReceipt(t)"
                                            >
                                                Print Receipt
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div v-if="transactions.data.length > 0" class="flex items-center justify-center gap-1">
                <template v-for="(link, index) in transactions.links" :key="index">
                    <button
                        v-if="link.url"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'border hover:bg-muted'"
                        v-html="link.label"
                        @click="goToPage(link.url)"
                    />
                    <span v-else class="rounded-md px-3 py-1.5 text-sm text-muted-foreground/50" v-html="link.label" />
                </template>
            </div>
        </div>

        <!-- Hidden on screen; TransactionReceipt's own @media print rules make
             it (and only it) visible when window.print() runs. -->
        <TransactionReceipt
            v-if="printingTransaction"
            :transaction="printingTransaction"
            class="hidden print:block"
        />
    </AppLayout>
</template>