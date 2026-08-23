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
    void_reason: '',
});
const requestForm = useForm({ type: 'transaction_correction', reason: '', target_type: 'transaction', target_id: 0, payload: { action: 'void', reason: '' } });
const { isAdmin } = usePermissions();

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) form.reset();
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
        requestForm.reason = `Request void of transaction ${props.transaction.transaction_no ?? `#${props.transaction.id}`}. Reason: ${form.void_reason}`;
        requestForm.target_id = props.transaction.id;
        requestForm.payload.reason = form.void_reason;
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

    form.patch(route('transactions.void', { transaction: props.transaction.id }), {
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
                <h2 class="text-lg font-semibold">{{ isAdmin ? 'Void Transaction' : 'Request Transaction Void' }}</h2>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="close">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="mb-4 space-y-1 rounded-md border bg-muted/30 p-3 text-sm">
                <p>
                    <span class="text-muted-foreground">Transaction:</span>
                    <span class="ml-1 font-mono font-medium">{{ transaction.transaction_no ?? `#${transaction.id}` }}</span>
                </p>
                <p>
                    <span class="text-muted-foreground">Date &amp; Time:</span>
                    <span class="ml-1 font-medium">{{ formatDateTime(transaction.created_at) }}</span>
                </p>
                <p>
                    <span class="text-muted-foreground">Total:</span>
                    <span class="ml-1 font-medium">{{ formatCurrency(transaction.total) }}</span>
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium">Void Reason</label>
                    <textarea
                        v-model="form.void_reason"
                        rows="3"
                        class="w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Why is this transaction being voided?"
                        required
                    />
                    <p v-if="form.errors.void_reason" class="mt-1 text-xs text-red-600">{{ form.errors.void_reason }}</p>
                </div>

                <p class="text-xs text-muted-foreground">
                    Voided By and Date &amp; Time are recorded automatically on submit.
                </p>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border px-4 py-2 text-sm hover:bg-muted" @click="close">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing || requestForm.processing ? 'Processing...' : isAdmin ? 'Confirm Void' : 'Send Request' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>