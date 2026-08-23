<script setup lang="ts">
import { Printer, X } from 'lucide-vue-next';
import { computed } from 'vue';

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

const props = defineProps<{
    open: boolean;
    transaction: HistoryTransaction | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'print-receipt', transaction: HistoryTransaction): void;
}>();

const isCash = computed(() => props.transaction?.payment_method === 'cash');
const isGcash = computed(() => props.transaction?.payment_method === 'gcash');

const statusStyles: Record<string, string> = {
    completed: 'bg-emerald-400/10 text-emerald-300 ring-1 ring-emerald-400/30',
    refunded: 'bg-amber-400/10 text-amber-300 ring-1 ring-amber-400/30',
    voided: 'bg-red-400/10 text-red-300 ring-1 ring-red-400/30',
};

const statusLabel: Record<string, string> = {
    completed: 'Completed',
    refunded: 'Refunded',
    voided: 'Voided',
};

function formatCurrency(value: number | null | undefined): string {
    return `₱${Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const formattedDateTime = computed(() => {
    if (!props.transaction?.created_at) return '';
    const date = new Date(props.transaction.created_at);
    if (Number.isNaN(date.getTime())) return '';

    const datePart = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const timePart = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

    return `${datePart} • ${timePart}`;
});

function handlePrint() {
    if (!props.transaction) return;
    emit('print-receipt', props.transaction);
}

function handleClose() {
    emit('close');
}
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="open && transaction"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="handleClose"
        >
            <Transition
                appear
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 scale-95 translate-y-2"
                enter-to-class="opacity-100 scale-100 translate-y-0"
            >
                <div
                    class="flex max-h-[90vh] w-full max-w-sm flex-col overflow-hidden rounded-2xl border border-[#2a5049] bg-[#173832] shadow-2xl shadow-black/40"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="transaction-details-title"
                >
                    <!-- Header -->
                    <div class="flex items-start justify-between gap-3 border-b border-[#2a5049] bg-[#0d2b25] px-6 py-5">
                        <div class="flex flex-col gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#9db8ae]">
                                Transaction Details
                            </span>
                            <div class="flex items-center gap-2">
                                <h2 id="transaction-details-title" class="font-mono text-base font-semibold text-[#d8a851]">
                                    {{ transaction.transaction_no }}
                                </h2>
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-[11px] font-medium"
                                    :class="statusStyles[transaction.status]"
                                >
                                    {{ statusLabel[transaction.status] }}
                                </span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-[#9db8ae] transition-colors hover:bg-[#173832] hover:text-[#f5efe0]"
                            @click="handleClose"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto px-6 py-5">
                        <dl class="flex flex-col divide-y divide-[#2a5049]/70">
                            <div class="flex items-center justify-between py-2.5">
                                <dt class="text-sm text-[#9db8ae]">Date &amp; Time</dt>
                                <dd class="text-sm font-medium text-[#f5efe0]">{{ formattedDateTime }}</dd>
                            </div>

                            <div class="flex items-center justify-between py-2.5">
                                <dt class="text-sm text-[#9db8ae]">Order Type</dt>
                                <dd class="text-sm font-medium capitalize text-[#f5efe0]">
                                    {{ transaction.order_type?.replace('_', ' ') }}
                                </dd>
                            </div>

                            <div v-if="transaction.customer_name" class="flex items-center justify-between py-2.5">
                                <dt class="text-sm text-[#9db8ae]">Customer</dt>
                                <dd class="text-sm font-medium text-[#f5efe0]">{{ transaction.customer_name }}</dd>
                            </div>

                            <div class="flex items-center justify-between py-2.5">
                                <dt class="text-sm text-[#9db8ae]">Payment Method</dt>
                                <dd class="text-sm font-medium text-[#f5efe0]">{{ isGcash ? 'GCash' : 'Cash' }}</dd>
                            </div>

                            <div v-if="isGcash" class="flex items-center justify-between py-2.5">
                                <dt class="text-sm text-[#9db8ae]">GCash Reference No.</dt>
                                <dd class="text-sm font-medium text-[#f5efe0]">{{ transaction.gcash_reference_number }}</dd>
                            </div>
                        </dl>

                        <!-- Items -->
                        <div class="mt-4 rounded-xl border border-[#2a5049] bg-[#0d2b25]">
                            <div class="border-b border-[#2a5049] px-4 py-2.5">
                                <span class="text-xs font-semibold uppercase tracking-wide text-[#9db8ae]">Items</span>
                            </div>
                            <ul class="divide-y divide-[#2a5049]/70">
                                <li v-for="(item, index) in transaction.items" :key="index" class="flex items-center justify-between px-4 py-2.5">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-[#f5efe0]">{{ item.product_name }}</span>
                                        <span class="text-xs text-[#9db8ae]">{{ item.quantity }} × {{ formatCurrency(item.price) }}</span>
                                    </div>
                                    <span class="text-sm font-medium text-[#f5efe0]">{{ formatCurrency(item.subtotal) }}</span>
                                </li>
                            </ul>
                        </div>

                        <dl class="mt-4 flex flex-col divide-y divide-[#2a5049]/70">
                            <div class="flex items-center justify-between py-2.5">
                                <dt class="text-sm text-[#9db8ae]">Total</dt>
                                <dd class="text-sm font-semibold text-[#f5efe0]">{{ formatCurrency(transaction.total) }}</dd>
                            </div>

                            <div class="flex items-center justify-between py-2.5">
                                <dt class="text-sm text-[#9db8ae]">Amount Paid</dt>
                                <dd class="text-sm font-medium text-[#f5efe0]">{{ formatCurrency(transaction.amount_received) }}</dd>
                            </div>

                            <div v-if="isCash" class="flex items-center justify-between py-2.5">
                                <dt class="text-sm text-[#9db8ae]">Change</dt>
                                <dd class="text-sm font-semibold text-emerald-400">{{ formatCurrency(transaction.change) }}</dd>
                            </div>
                        </dl>

                        <p v-if="transaction.notes" class="mt-4 rounded-lg bg-[#0d2b25] px-4 py-3 text-xs text-[#9db8ae]">
                            {{ transaction.notes }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="border-t border-[#2a5049] px-6 py-5">
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-[#2a5049] bg-[#0d2b25] px-4 py-2.5 text-sm font-medium text-[#f5efe0] transition-colors hover:border-[#3d6b62]"
                            @click="handlePrint"
                        >
                            <Printer class="h-4 w-4" />
                            Print Receipt
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>