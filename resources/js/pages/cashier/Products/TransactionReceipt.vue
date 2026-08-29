<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

interface ReceiptItem {
    product_name: string;
    quantity: number;
    price: number;
    subtotal: number;
}

interface ReceiptTransaction {
    id: number;
    transaction_no: string;
    order_number: number | null;
    created_at: string;
    order_type?: 'dine_in' | 'take_out' | null;
    payment_method: 'cash' | 'gcash';
    amount_received: number;
    change: number;
    total: number;
    customer_name?: string | null;
    notes?: string | null;
    gcash_reference_number?: string | null;
    user?: { name: string } | null;
    items: ReceiptItem[];
}

const props = withDefaults(
    defineProps<{
        transaction: ReceiptTransaction;
        shopName?: string;
        addressLine?: string;
        cityLine?: string;
        phone?: string;
        storeCode?: string;
    }>(),
    {
        shopName: (usePage().props.shop as { name?: string })?.name ?? 'JC66 Coffee Shop',
        addressLine: 'Street Address',
        cityLine: 'City, ZIP',
        phone: '+63 000-000-0000',
        storeCode: '00001',
    },
);

const formattedDate = computed(() => {
    const date = new Date(props.transaction.created_at);
    if (Number.isNaN(date.getTime())) return { date: '', time: '' };

    return {
        date: date.toLocaleDateString('en-PH', { month: '2-digit', day: '2-digit', year: 'numeric' }),
        time: date.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true }),
    };
});

const itemCount = computed(() => props.transaction.items.reduce((sum, i) => sum + i.quantity, 0));

function money(value: number): string {
    return `₱${Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function orderTypeLabel(type: string | null | undefined): string {
    return type === 'dine_in' ? 'Dine-in' : type === 'take_out' ? 'Take-out' : '';
}

// Deterministic decorative barcode bars, derived from the transaction number
// so the same receipt always renders the same pattern. Not scannable —
// swap in a real barcode library (e.g. JsBarcode) if you need this to
// actually scan.
const barcodeBars = computed(() => {
    const source = String(props.transaction.transaction_no || props.transaction.id).padEnd(24, '0');
    const bars: { width: number }[] = [];
    for (let i = 0; i < source.length; i++) {
        const code = source.charCodeAt(i);
        bars.push({ width: (code % 3) + 1 });
    }
    return bars;
});
</script>

<template>
    <div class="receipt">
        <!-- Header -->
        <h1 class="shop-name">{{ shopName }}</h1>
        <p class="header-line">{{ addressLine }}</p>
        <p class="header-line">{{ cityLine }}</p>
        <p class="header-line">Tel.: {{ phone }}</p>

        <div class="dashed-rule" />

        <!-- Meta -->
        <div class="meta">
            <div class="meta-row">
                <span>Store: {{ storeCode }}</span>
                <span>{{ formattedDate.date }} &nbsp; {{ formattedDate.time }}</span>
            </div>
            <div class="meta-row">
                <span>Server: {{ transaction.user?.name ?? '—' }}</span>
            </div>
            <div class="meta-row">
                <span>Ref #: {{ transaction.transaction_no }}</span>
            </div>
            <div v-if="transaction.order_number" class="meta-row">
                <span>Order #: {{ String(transaction.order_number).padStart(3, '0') }}</span>
            </div>
            <div v-if="orderTypeLabel(transaction.order_type)" class="meta-row">
                <span>Type: {{ orderTypeLabel(transaction.order_type) }}</span>
            </div>
            <div v-if="transaction.customer_name" class="meta-row">
                <span>Customer: {{ transaction.customer_name }}</span>
            </div>
        </div>

        <div class="dashed-rule" />

        <!-- Items -->
        <div class="items-header">
            <span class="col-name">Name</span>
            <span class="col-qty">Qty</span>
            <span class="col-price">Price</span>
        </div>

        <div v-for="(item, idx) in transaction.items" :key="idx" class="item-row">
            <span class="col-name">{{ item.product_name }}</span>
            <span class="col-qty">{{ item.quantity }}</span>
            <span class="col-price">{{ money(item.price) }}</span>
        </div>

        <div class="dashed-rule" />

        <!-- Totals -->
        <div class="total-row">
            <span>Price</span>
            <span>{{ money(transaction.total) }}</span>
        </div>

        <div class="payment-row">
            <span>{{ transaction.payment_method === 'gcash' ? 'GCASH' : 'CASH' }}</span>
            <span>{{ money(transaction.amount_received) }}</span>
        </div>
        <div v-if="transaction.payment_method === 'gcash' && transaction.gcash_reference_number" class="payment-row ref-row">
            <span>Ref #</span>
            <span>{{ transaction.gcash_reference_number }}</span>
        </div>
        <div class="payment-row">
            <span>CHANGE</span>
            <span>{{ money(transaction.change) }}</span>
        </div>

        <p class="item-count-note">{{ itemCount }} item{{ itemCount === 1 ? '' : 's' }}</p>

        <p v-if="transaction.notes" class="notes-line">Note: {{ transaction.notes }}</p>

        <div class="dashed-rule center-rule" />

        <p class="thank-you">THANK YOU!</p>

        <!-- Barcode -->
        <div class="barcode" aria-hidden="true">
            <span v-for="(bar, idx) in barcodeBars" :key="idx" class="bar" :style="{ width: bar.width + 'px' }" />
        </div>
        <p class="barcode-number">{{ transaction.transaction_no }}</p>
    </div>
</template>

<style scoped>
.receipt {
    display: none;
    width: 80mm;
    max-width: 80mm;
    box-sizing: border-box;
    margin: 0;
    padding: 5mm;
    background: #ffffff;
    color: #1a1a1a;
    font-family: 'Courier New', ui-monospace, monospace;
    font-size: 13px;
    line-height: 1.5;
}

.shop-name {
    text-align: center;
    font-family: 'Arial Black', 'Helvetica Neue', sans-serif;
    font-weight: 800;
    font-size: 26px;
    letter-spacing: -0.02em;
    margin: 0 0 14px;
}

.header-line {
    text-align: center;
    margin: 0 0 4px;
    font-size: 13px;
}

.dashed-rule {
    border-top: 2px dotted #1a1a1a;
    margin: 16px 0;
}

.dashed-rule.center-rule {
    width: 60%;
    margin: 20px auto;
}

.meta-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 4px;
}

.items-header {
    display: flex;
    font-weight: 700;
    margin-bottom: 12px;
}

.item-row {
    display: flex;
    margin-bottom: 10px;
}

.col-name {
    flex: 1;
    padding-right: 8px;
}

.col-qty {
    width: 40px;
    text-align: right;
}

.col-price {
    width: 76px;
    text-align: right;
}

.total-row {
    display: flex;
    justify-content: space-between;
    font-weight: 800;
    font-size: 19px;
    margin-bottom: 10px;
}

.payment-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 4px;
}

.payment-row.ref-row {
    font-size: 11px;
    color: #6b6b6b;
}

.item-count-note {
    text-align: right;
    color: #6b6b6b;
    font-size: 11px;
    margin: 10px 0 0;
}

.notes-line {
    color: #4a4a4a;
    font-size: 11px;
    margin: 8px 0 0;
    word-break: break-word;
}

.thank-you {
    text-align: center;
    font-weight: 800;
    font-size: 15px;
    margin: 0 0 20px;
}

.barcode {
    display: flex;
    align-items: stretch;
    justify-content: center;
    gap: 2px;
    height: 56px;
    margin-bottom: 6px;
}

.bar {
    background: #1a1a1a;
}

.barcode-number {
    text-align: center;
    font-size: 11px;
    letter-spacing: 0.15em;
    color: #4a4a4a;
    margin: 0;
}

/* Printing: fit an 80mm thermal roll, strip page chrome */
@media print {
    @page {
        size: 80mm auto;
        margin: 0;
    }

    :global(html),
    :global(body) {
        width: 80mm;
        margin: 0;
        padding: 0;
    }

    :global(body *) {
        visibility: hidden;
    }

    .receipt,
    .receipt * {
        visibility: visible;
    }
    
    .receipt {
        display: block !important;
        position: absolute;
        top: 0;
        left: 0;
        width: 80mm;
        max-width: 80mm;
        padding: 5mm;
        box-sizing: border-box;
    }
}
</style>