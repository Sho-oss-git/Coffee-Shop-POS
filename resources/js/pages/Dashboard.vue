<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head } from '@inertiajs/vue3';
import ClockStatusToggle from '@/components/ClockStatusToggle.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { ArrowUpRight, Banknote, ClipboardList, Package, ShoppingBag, TrendingUp } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

defineProps<{
    summary: { sales: number; transactions: number; items: number; average_sale: number };
    recentTransactions: { id: number; time: string; cashier: string; total: number; payment_method: string }[];
    pendingRequests: number;
    productCount: number;
    date: string;
}>();

const page = usePage<SharedData>();
const currentTime = ref('');
let clockTimer: ReturnType<typeof setInterval> | undefined;

function updateClock() {
    currentTime.value = new Date().toLocaleTimeString('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
}

onMounted(() => {
    updateClock();
    clockTimer = setInterval(updateClock, 1000);
});

onUnmounted(() => {
    if (clockTimer) clearInterval(clockTimer);
});

function money(value: number) {
    return `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-col gap-6 bg-muted/20 p-3 sm:p-4 lg:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">{{ date }}</p>
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Operations Dashboard</h1>
                    <p class="text-sm text-muted-foreground">A quick view of today’s activity.</p>
                </div>
                <div class="flex w-full flex-wrap items-center justify-between gap-3 sm:w-auto sm:justify-end">
                    <span class="text-sm font-semibold tabular-nums text-muted-foreground" aria-live="polite">{{ currentTime }}</span>
                    <a v-if="page.props.auth.user.role !== 'cashier'" href="/reports/sales" class="inline-flex w-full items-center justify-center gap-2 rounded-md border bg-background px-3 py-2 text-sm font-medium shadow-sm hover:bg-muted sm:w-auto">
                        View sales report <ArrowUpRight class="h-4 w-4" />
                    </a>
                </div>
            </div>

            <div
                v-if="page.props.auth.user.role !== 'admin'"
                class="flex items-center justify-between rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
            >
                <div>
                    <p class="text-sm text-muted-foreground">Your status</p>
                    <StatusBadge :status="page.props.auth.user.status" />
                </div>
                <ClockStatusToggle :status="page.props.auth.user.status" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="metric in [
                    { label: 'Today’s sales', value: money(summary.sales), icon: Banknote, tone: 'text-emerald-600', surface: 'bg-emerald-500/10' },
                    { label: 'Transactions', value: summary.transactions, icon: ShoppingBag, tone: 'text-sky-600', surface: 'bg-sky-500/10' },
                    { label: 'Items sold', value: summary.items, icon: Package, tone: 'text-amber-600', surface: 'bg-amber-500/10' },
                    { label: 'Average sale', value: money(summary.average_sale), icon: TrendingUp, tone: 'text-violet-600', surface: 'bg-violet-500/10' },
                ]" :key="metric.label" class="flex items-start justify-between rounded-xl border bg-background p-4 shadow-sm">
                    <div><p class="text-sm text-muted-foreground">{{ metric.label }}</p><p class="mt-2 text-2xl font-semibold">{{ metric.value }}</p></div>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg" :class="metric.surface">
                        <component :is="metric.icon" class="h-5 w-5" :class="metric.tone" />
                    </span>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <section class="rounded-xl border bg-background shadow-sm">
                    <div class="flex items-center justify-between border-b p-4"><div><h2 class="font-semibold">Recent transactions</h2><p class="text-xs text-muted-foreground">Completed today</p></div><ShoppingBag class="h-5 w-5 text-muted-foreground" /></div>
                    <div v-if="recentTransactions.length" class="divide-y">
                        <div v-for="transaction in recentTransactions" :key="transaction.id" class="flex items-center justify-between gap-4 p-4">
                            <div><p class="font-medium">{{ transaction.cashier }}</p><p class="text-xs text-muted-foreground">{{ transaction.time }} · {{ transaction.payment_method === 'gcash' ? 'GCash' : 'Cash' }}</p></div>
                            <p class="font-semibold">{{ money(transaction.total) }}</p>
                        </div>
                    </div>
                    <p v-else class="p-8 text-center text-sm text-muted-foreground">No completed transactions today.</p>
                </section>

                <section class="flex flex-col gap-4 rounded-xl border bg-background p-4 shadow-sm">
                    <div><h2 class="font-semibold">Work queue</h2><p class="text-xs text-muted-foreground">Items that may need attention.</p></div>
                    <a href="/action-requests" class="flex items-center justify-between rounded-lg border p-4 hover:bg-muted/50"><span class="flex items-center gap-3"><ClipboardList class="h-5 w-5 text-amber-600" /><span><span class="block font-medium">Action Requests</span><span class="text-xs text-muted-foreground">Pending Admin review</span></span></span><span class="text-xl font-semibold">{{ pendingRequests }}</span></a>
                    <a :href="page.props.auth.user.role === 'cashier' ? route('cashier.products.index') : route('products.index')" class="flex items-center justify-between rounded-lg border p-4 hover:bg-muted/50"><span class="flex items-center gap-3"><Package class="h-5 w-5 text-sky-600" /><span><span class="block font-medium">Available Products</span><span class="text-xs text-muted-foreground">Currently listed for sale</span></span></span><span class="text-xl font-semibold">{{ productCount }}</span></a>
                </section>
            </div>
        </div>
    </AppLayout>
</template>