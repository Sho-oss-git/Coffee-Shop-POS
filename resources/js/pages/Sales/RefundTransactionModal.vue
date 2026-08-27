<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { watch } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

interface TransactionSummary {
    id: number;
    transaction_no?: string;
    total: number;
    created_at: string;
}

const props = defineProps<{
    open: boolean;
    transaction: TransactionSummary | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const form = useForm({
    refund_amount: '' as number | string,
    refund_reason: '',
});
const requestForm = useForm({ type: 'transaction_correction', reason: '', target_type: 'transaction', target_id: 0, payload: { action: 'refund', refund_amount: 0, reason: '' } });
const { isAdmin } = usePermissions();

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.transaction) {
            form.reset();
            form.refund_amount = props.transaction.total;
        }
    },
);

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

function submit() {
    if (!props.transaction) return;

    if (!isAdmin.value) {
        requestForm.reason = `Request refund of ${formatCurrency(Number(form.refund_amount))} for transaction ${props.transaction.transaction_no ?? `#${props.transaction.id}`}. Reason: ${form.refund_reason}`;
        requestForm.target_id = props.transaction.id;
        requestForm.payload.refund_amount = Number(form.refund_amount);
        requestForm.payload.reason = form.refund_reason;
        requestForm.post(route('action-requests.store'), {
            preserveScroll: true,
            onSuccess: () => {
                requestForm.reset();
                form.reset();
                emit('close');
            },
        });
        return;
    }

    form.patch(route('transactions.refund', { transaction: props.transaction.id }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
}

function close() {
    form.clearErrors();
    emit('close');
}
</script>

<template>
    <div v-if="open && transaction" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-lg border bg-background p-5 shadow-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-foreground">{{ isAdmin ? 'Refund Transaction' : 'Request Transaction Refund' }}</h2>
                <button type="button" class="text-foreground/60 hover:text-foreground" @click="close">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="mb-4 space-y-1 rounded-md border bg-muted/30 p-3 text-sm">
                <p>
                    <span class="text-foreground/70">Original Transaction:</span>
                    <span class="ml-1 font-mono font-medium text-foreground">{{ transaction.transaction_no ?? `#${transaction.id}` }}</span>
                </p>
                <p>
                    <span class="text-foreground/70">Date & Time:</span>
                    <span class="ml-1 font-medium text-foreground">{{ formatDateTime(transaction.created_at) }}</span>
                </p>
                <p>
                    <span class="text-foreground/70">Original Total:</span>
                    <span class="ml-1 font-medium text-foreground">{{ formatCurrency(transaction.total) }}</span>
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Refund Amount</label>
                    <input
                        v-model="form.refund_amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :max="transaction.total"
                        class="w-full rounded-md border px-3 py-2 text-sm"
                        required
                    />
                    <p v-if="form.errors.refund_amount" class="mt-1 text-xs text-red-600">{{ form.errors.refund_amount }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Refund Reason</label>
                    <textarea
                        v-model="form.refund_reason"
                        rows="3"
                        class="w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Why is this transaction being refunded?"
                        required
                    />
                    <p v-if="form.errors.refund_reason" class="mt-1 text-xs text-red-600">{{ form.errors.refund_reason }}</p>
                </div>

                <p class="text-xs text-foreground/60">
                    Processed By and Date & Time are recorded automatically on submit.
                </p>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border px-4 py-2 text-sm text-foreground hover:bg-muted" @click="close">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing || requestForm.processing ? 'Processing...' : isAdmin ? 'Confirm Refund' : 'Send Request' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>