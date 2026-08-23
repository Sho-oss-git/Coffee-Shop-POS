<script setup lang="ts">
import { CheckCircle2, Printer, ReceiptText } from 'lucide-vue-next';
import { computed } from 'vue';

interface CompletedTransaction {
    id: number;
    order_number: number;
    transaction_no: string;
    payment_method: 'cash' | 'gcash';
    total: number;
    amount_received: number;
    change: number;
    gcash_reference_number: string | null;
    cashier: string;
    created_at: string;
    order_type?: 'dine_in' | 'take_out' | null;
    customer_name?: string | null;
    notes?: string | null;
    user?: { name: string } | null;
    items: {
        product_name: string;
        quantity: number;
        price: number;
        subtotal: number;
    }[];
}

const props = defineProps<{
    open: boolean;
    transaction: CompletedTransaction | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'print-receipt', transaction: CompletedTransaction): void;
    (e: 'new-transaction'): void;
}>();

const transactionNumber = computed(() => props.transaction?.transaction_no ?? '');
const isCash = computed(() => props.transaction?.payment_method === 'cash');
const isGcash = computed(() => props.transaction?.payment_method === 'gcash');

function formatCurrency(value: number | null | undefined): string {
    return `₱${Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const formattedDateTime = computed(() => {
    if (!props.transaction?.created_at) return '';
    const date = new Date(props.transaction.created_at);
    if (Number.isNaN(date.getTime())) return '';

    return `${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} • ${date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}`;
});

function handlePrint() {
    if (!props.transaction) return;
    emit('print-receipt', props.transaction);
}

function handleNewTransaction() {
    emit('new-transaction');
}

function handleBackdropClose() {
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
            class="transaction-complete fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="handleBackdropClose"
        >
            <Transition
                appear
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 scale-95 translate-y-2"
                enter-to-class="opacity-100 scale-100 translate-y-0"
            >
                <div
                    class="flex w-full max-w-sm flex-col overflow-hidden rounded-2xl border border-[#2a5049] bg-[#173832] shadow-2xl shadow-black/40"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="transaction-complete-title"
                >
                    <!-- Header -->
                    <div class="flex flex-col items-center gap-2 border-b border-[#2a5049] bg-[#0d2b25] px-6 py-6 text-center">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#9db8ae]">Transaction Complete</span>
                        <div class="flex items-center gap-2">
                            <CheckCircle2 class="h-6 w-6 text-emerald-400" />
                            <h2 id="transaction-complete-title" class="text-lg font-semibold text-[#f5efe0]">Payment Successful</h2>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="flex flex-col gap-4 px-6 py-5">
                        <div class="flex items-center justify-between rounded-xl border border-[#2a5049] bg-[#0d2b25] px-4 py-3">
                            <span class="text-xs font-medium uppercase tracking-wide text-[#9db8ae]">Transaction No.</span>
                            <span class="font-mono text-sm font-semibold text-[#d8a851]">{{ transactionNumber }}</span>
                        </div>
                        <dl class="flex flex-col divide-y divide-[#2a5049]/70">
                            <div class="flex items-center justify-between py-2.5"><dt class="text-sm text-[#9db8ae]">Payment Method</dt><dd class="text-sm font-medium text-[#f5efe0]">{{ isGcash ? 'GCash' : 'Cash' }}</dd></div>
                            <div class="flex items-center justify-between py-2.5"><dt class="text-sm text-[#9db8ae]">Total</dt><dd class="text-sm font-semibold text-[#f5efe0]">{{ formatCurrency(transaction.total) }}</dd></div>
                            <div class="flex items-center justify-between py-2.5"><dt class="text-sm text-[#9db8ae]">Amount Paid</dt><dd class="text-sm font-medium text-[#f5efe0]">{{ formatCurrency(transaction.amount_received) }}</dd></div>
                            <div v-if="isCash" class="flex items-center justify-between py-2.5"><dt class="text-sm text-[#9db8ae]">Change</dt><dd class="text-sm font-semibold text-emerald-400">{{ formatCurrency(transaction.change) }}</dd></div>
                            <div v-if="isGcash" class="flex items-center justify-between py-2.5"><dt class="text-sm text-[#9db8ae]">GCash Reference No.</dt><dd class="text-sm font-medium text-[#f5efe0]">{{ transaction.gcash_reference_number }}</dd></div>
                            <div class="flex items-center justify-between py-2.5"><dt class="text-sm text-[#9db8ae]">Date &amp; Time</dt><dd class="text-sm font-medium text-[#f5efe0]">{{ formattedDateTime }}</dd></div>
                            <div class="flex items-center justify-between py-2.5"><dt class="text-sm text-[#9db8ae]">Cashier</dt><dd class="text-sm font-medium text-[#f5efe0]">{{ transaction.cashier }}</dd></div>
                        </dl>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col gap-2 border-t border-[#2a5049] px-6 py-5">
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-xl border border-[#2a5049] bg-[#0d2b25] px-4 py-2.5 text-sm font-medium text-[#f5efe0] transition-colors hover:border-[#3d6b62]"
                            @click="handlePrint"
                        >
                            <Printer class="h-4 w-4" />
                            Print Receipt
                        </button>
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-xl bg-[#d8a851] px-4 py-2.5 text-sm font-semibold text-[#0d2b25] transition-colors hover:bg-[#e0b566]"
                            @click="handleNewTransaction"
                        >
                            <ReceiptText class="h-4 w-4" />
                            New Transaction
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

