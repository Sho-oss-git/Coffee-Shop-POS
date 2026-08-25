<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ChevronDown, ChevronRight, History, Wallet, X } from 'lucide-vue-next';
import RefundTransactionModal from './RefundTransactionModal.vue';
import VoidTransactionModal from './VoidTransactionModal.vue';

interface TransactionItem {
    id: number;
    product_id: number;
    product_name: string;
    price: number;
    quantity: number;
    subtotal: number;
    unit_cost: number | null;
    cogs: number | null;
}

interface TransactionRow {
    id: number;
    order_number: number | null;
    order_type: 'dine_in' | 'take_out' | null;
    customer_name: string | null;
    notes: string | null;
    total: number;
    amount_received: number;
    change: number;
    status: string;
    payment_method: 'cash' | 'gcash' | null;
    gcash_reference_number: string | null;
    gcash_proof_url: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
    items: TransactionItem[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTransactions {
    data: TransactionRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    transactions: PaginatedTransactions;
    filters: { date?: string; status?: string; payment_method?: string };
}>();

const date = ref(props.filters.date ?? '');
const status = ref(props.filters.status ?? '');
const paymentMethod = ref(props.filters.payment_method ?? '');
const expandedId = ref<number | null>(null);

function applyFilters() {
    router.get(
        route('sales-history'),
        {
            date: date.value || undefined,
            status: status.value || undefined,
            payment_method: paymentMethod.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearFilters() {
    date.value = '';
    status.value = '';
    paymentMethod.value = '';
    applyFilters();
}

function toggleExpanded(id: number) {
    expandedId.value = expandedId.value === id ? null : id;
}

function goToPage(url: string | null) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

function money(value: number | string) {
    return `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDateTime(value: string) {
    return new Date(value).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function statusClasses(s: string) {
    switch (s) {
        case 'completed':
            return 'bg-emerald-100 text-emerald-700';
        case 'voided':
            return 'bg-red-100 text-red-700';
        case 'refunded':
            return 'bg-amber-100 text-amber-700';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

function paymentLabel(method: string | null) {
    return method === 'gcash' ? 'GCash' : 'Cash';
}

function orderTypeLabel(type: string | null) {
    return type === 'dine_in' ? 'Dine-in' : type === 'take_out' ? 'Take-out' : '—';
}

const hasActiveFilters = () => !!(date.value || status.value || paymentMethod.value);

/* --- Refund / Void modals --- */
const showRefund = ref(false);
const showVoid = ref(false);
const selectedTransaction = ref<TransactionRow | null>(null);

function openRefund(transaction: TransactionRow) {
    selectedTransaction.value = transaction;
    showRefund.value = true;
}

function closeRefund() {
    showRefund.value = false;
}

function openVoid(transaction: TransactionRow) {
    selectedTransaction.value = transaction;
    showVoid.value = true;
}

function closeVoid() {
    showVoid.value = false;
}
</script>

<template>
    <Head title="Sales History" />

    <AppLayout>
        <div class="flex min-w-0 flex-col gap-6 bg-muted/20 p-3 sm:p-4 lg:p-6">
            <!-- Header -->
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">All records</p>
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Sales History</h1>
                    <p class="text-sm text-muted-foreground">Browse and filter all past transactions.</p>
                </div>
            </div>

            <!-- Filter bar -->
            <div class="flex flex-col gap-3 rounded-xl border bg-background p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-center">
                <input
                    v-model="date"
                    type="date"
                    @change="applyFilters"
                    class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground dark:[color-scheme:dark] sm:w-auto"
                />

                <select
                    v-model="status"
                    @change="applyFilters"
                    class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground capitalize dark:[color-scheme:dark] sm:w-auto"
                >
                    <option value="">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="voided">Voided</option>
                    <option value="refunded">Refunded</option>
                </select>

                <select
                    v-model="paymentMethod"
                    @change="applyFilters"
                    class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground dark:[color-scheme:dark] sm:w-auto"
                >
                    <option value="">All Payment Methods</option>
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                </select>

                <button
                    v-if="hasActiveFilters()"
                    @click="clearFilters"
                    class="flex items-center gap-1 rounded-md border px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted sm:ml-auto"
                >
                    <X class="h-3.5 w-3.5" />
                    Clear
                </button>
            </div>

            <!-- Transactions table -->
            <section class="overflow-hidden rounded-xl border bg-background shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b p-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-500/10">
                            <History class="h-5 w-5 text-sky-600" />
                        </span>
                        <div>
                            <h2 class="font-semibold">Transactions</h2>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="transactions.total">
                                    Showing {{ transactions.from }}–{{ transactions.to }} of {{ transactions.total }}
                                </template>
                                <template v-else>No transactions found</template>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="w-8 p-2"></th>
                                <th class="p-2 text-left">Transaction</th>
                                <th class="p-2 text-left">Date &amp; Time</th>
                                <th class="p-2 text-left">Cashier</th>
                                <th class="p-2 text-left">Payment</th>
                                <th class="p-2 text-right">Items</th>
                                <th class="p-2 text-right">Total</th>
                                <th class="p-2 text-right">Received</th>
                                <th class="p-2 text-right">Change</th>
                                <th class="p-2 text-left">Status</th>
                                <th class="p-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="t in transactions.data" :key="t.id">
                                <tr class="cursor-pointer border-t hover:bg-muted/30" @click="toggleExpanded(t.id)">
                                    <td class="p-2 text-muted-foreground">
                                        <ChevronDown v-if="expandedId === t.id" class="h-4 w-4" />
                                        <ChevronRight v-else class="h-4 w-4" />
                                    </td>
                                    <td class="p-2 font-medium">#{{ t.id }}</td>
                                    <td class="p-2">{{ formatDateTime(t.created_at) }}</td>
                                    <td class="p-2">{{ t.user?.name ?? '—' }}</td>
                                    <td class="p-2">
                                        <span class="inline-flex items-center gap-1 text-xs">
                                            <Wallet class="h-3.5 w-3.5" :class="t.payment_method === 'gcash' ? 'text-amber-500' : 'text-emerald-600'" />
                                            {{ paymentLabel(t.payment_method) }}
                                        </span>
                                    </td>
                                    <td class="p-2 text-right">{{ t.items.reduce((sum, i) => sum + i.quantity, 0) }}</td>
                                    <td class="p-2 text-right font-medium">{{ money(t.total) }}</td>
                                    <td class="p-2 text-right">{{ money(t.amount_received) }}</td>
                                    <td class="p-2 text-right">{{ money(t.change) }}</td>
                                    <td class="p-2">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClasses(t.status)">
                                            {{ t.status }}
                                        </span>
                                    </td>
                                    <td class="p-2">
                                        <div v-if="t.status === 'completed'" class="flex gap-1" @click.stop>
                                            <button
                                                type="button"
                                                class="rounded-md border px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50"
                                                @click="openRefund(t)"
                                            >
                                                Refund
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md border px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                                                @click="openVoid(t)"
                                            >
                                                Void
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="expandedId === t.id" class="border-t bg-muted/20">
                                    <td colspan="11" class="p-0">
                                        <div class="flex flex-col gap-4 p-4 lg:flex-row">
                                            <!-- Line items -->
                                            <div class="flex-1">
                                                <table class="w-full text-xs">
                                                    <thead>
                                                        <tr class="text-muted-foreground">
                                                            <th class="p-1.5 text-left">Product</th>
                                                            <th class="p-1.5 text-right">Price</th>
                                                            <th class="p-1.5 text-right">Qty</th>
                                                            <th class="p-1.5 text-right">Subtotal</th>
                                                            <th class="p-1.5 text-right">Unit Cost</th>
                                                            <th class="p-1.5 text-right">COGS</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="item in t.items" :key="item.id" class="border-t border-border/60">
                                                            <td class="p-1.5">{{ item.product_name }}</td>
                                                            <td class="p-1.5 text-right">{{ money(item.price) }}</td>
                                                            <td class="p-1.5 text-right">{{ item.quantity }}</td>
                                                            <td class="p-1.5 text-right">{{ money(item.subtotal) }}</td>
                                                            <td class="p-1.5 text-right">
                                                                {{ item.unit_cost !== null ? money(item.unit_cost) : '—' }}
                                                            </td>
                                                            <td class="p-1.5 text-right">
                                                                {{ item.cogs !== null ? money(item.cogs) : '—' }}
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>

                                                <!-- Order details -->
                                                <div
                                                    v-if="t.order_number || t.customer_name || t.notes"
                                                    class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted-foreground"
                                                >
                                                    <span v-if="t.order_number">
                                                        Order: <span class="font-medium text-foreground">#{{ String(t.order_number).padStart(3, '0') }}</span>
                                                    </span>
                                                    <span v-if="t.order_type">
                                                        Type: <span class="font-medium text-foreground">{{ orderTypeLabel(t.order_type) }}</span>
                                                    </span>
                                                    <span v-if="t.customer_name">
                                                        Customer: <span class="font-medium text-foreground">{{ t.customer_name }}</span>
                                                    </span>
                                                    <span v-if="t.notes" class="w-full">
                                                        Notes: <span class="font-medium text-foreground">{{ t.notes }}</span>
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- GCash proof panel — only for GCash payments -->
                                            <div v-if="t.payment_method === 'gcash'" class="w-full shrink-0 lg:w-64">
                                                <div class="rounded-lg border p-3">
                                                    <p class="mb-2 text-xs font-medium text-muted-foreground">GCash Payment</p>

                                                    <p class="text-xs">
                                                        Reference #:
                                                        <span class="font-medium">{{ t.gcash_reference_number ?? '—' }}</span>
                                                    </p>
                                                    <p class="mt-1 text-xs">
                                                        Amount Paid: <span class="font-medium">{{ money(t.amount_received) }}</span>
                                                    </p>

                                                    <a
                                                        v-if="t.gcash_proof_url"
                                                        :href="t.gcash_proof_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="mt-2 block overflow-hidden rounded-md border"
                                                    >
                                                        <img :src="t.gcash_proof_url" alt="GCash payment proof" class="h-32 w-full object-cover" />
                                                    </a>
                                                    <p v-else class="mt-2 text-xs italic text-muted-foreground">No proof image uploaded</p>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!transactions.data.length">
                                <td colspan="11" class="p-6 text-center text-muted-foreground">No transactions match these filters</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Pagination -->
            <div v-if="transactions.last_page > 1" class="flex items-center justify-center gap-1">
                <template v-for="(link, index) in transactions.links" :key="index">
                    <button
                        v-if="link.url"
                        @click="goToPage(link.url)"
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'border bg-background hover:bg-muted'"
                        v-html="link.label"
                    />
                    <span v-else class="rounded-md px-3 py-1.5 text-sm text-muted-foreground/50" v-html="link.label" />
                </template>
            </div>
        </div>

        <RefundTransactionModal :open="showRefund" :transaction="selectedTransaction" @close="closeRefund" />
        <VoidTransactionModal :open="showVoid" :transaction="selectedTransaction" @close="closeVoid" />
    </AppLayout>
</template>