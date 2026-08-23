<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Coffee, ShoppingBag } from 'lucide-vue-next';
import { ref } from 'vue';

export interface OrderDetails {
    orderType: 'dine_in' | 'take_out';
    customerName: string;
    notes: string;
}

const props = defineProps<{
    orderNumber: number | null;
}>();

const emit = defineEmits<{
    confirm: [OrderDetails];
}>();

const isOpen = ref(false);

const orderType = ref<'dine_in' | 'take_out'>('dine_in');
const customerName = ref('');
const notes = ref('');

/** Pass the current details (if editing an already-set order) or leave empty for a fresh one. */
function open(existing?: OrderDetails | null) {
    orderType.value = existing?.orderType ?? 'dine_in';
    customerName.value = existing?.customerName ?? '';
    notes.value = existing?.notes ?? '';
    isOpen.value = true;
}

function close() {
    isOpen.value = false;
}

function confirm() {
    emit('confirm', {
        orderType: orderType.value,
        customerName: customerName.value.trim(),
        notes: notes.value.trim(),
    });
    isOpen.value = false;
}

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-h-[85vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Order Details</DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
                <div class="rounded-lg bg-muted px-3 py-2 text-sm text-muted-foreground">
                    Order Number:
                    <span class="font-semibold text-foreground">
                        {{ props.orderNumber !== null ? `#${String(props.orderNumber).padStart(3, '0')}` : '—' }}
                    </span>
                </div>

                <div class="space-y-1.5">
                    <Label>Dine-in or Take-out</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-lg border-2 px-3 py-2.5 text-sm font-medium transition-colors"
                            :class="
                                orderType === 'dine_in'
                                    ? 'border-[#d8a851] bg-[#d8a851]/10 text-[#173832] dark:text-[#f5efe0]'
                                    : 'border-input text-muted-foreground hover:bg-muted'
                            "
                            @click="orderType = 'dine_in'"
                        >
                            <Coffee class="h-4 w-4" />
                            Dine-in
                        </button>
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-lg border-2 px-3 py-2.5 text-sm font-medium transition-colors"
                            :class="
                                orderType === 'take_out'
                                    ? 'border-[#d8a851] bg-[#d8a851]/10 text-[#173832] dark:text-[#f5efe0]'
                                    : 'border-input text-muted-foreground hover:bg-muted'
                            "
                            @click="orderType = 'take_out'"
                        >
                            <ShoppingBag class="h-4 w-4" />
                            Take-out
                        </button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="customer-name">Customer Name <span class="font-normal text-muted-foreground">(optional)</span></Label>
                    <Input id="customer-name" v-model="customerName" type="text" placeholder="e.g. Juan" maxlength="255" />
                </div>

                <div class="space-y-1.5">
                    <Label for="order-notes">
                        Special Instructions / Notes <span class="font-normal text-muted-foreground">(optional)</span>
                    </Label>
                    <textarea
                        id="order-notes"
                        v-model="notes"
                        rows="3"
                        maxlength="500"
                        placeholder="e.g. Less sugar, extra ice, no straw"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    ></textarea>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="close">Cancel</Button>
                <Button type="button" @click="confirm">Save Order Details</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>