<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface LogRow {
    id: number;
    quantity_change: number;
    note: string | null;
    created_at: string;
    ingredient: { id: number; name: string; unit: string } | null;
    ingredient_batch: { id: number; received_date: string | null; expiry_date: string | null } | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    logs: Paginated<LogRow>;
    ingredients: { id: number; name: string }[];
    filters: { ingredient_id?: string; date_from?: string; date_to?: string };
}>();

const ingredientId = ref(props.filters.ingredient_id ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

function applyFilters() {
    router.get(
        route('reports.restock-history'),
        {
            ingredient_id: ingredientId.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function formatDate(value: string) {
    return new Date(value).toLocaleDateString('en-PH', { dateStyle: 'medium' });
}
</script>

<template>
    <Head title="Restock History" />

    <AppLayout>
        <div class="p-4 space-y-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Restock History</h1>
                <p class="text-sm text-foreground/60">All batches received, most recent first</p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="text-xs text-foreground/60">Ingredient</label>
                    <select v-model="ingredientId" @change="applyFilters" class="block rounded-md border px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        <option v-for="i in ingredients" :key="i.id" :value="i.id">{{ i.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-foreground/60">From</label>
                    <input v-model="dateFrom" type="date" @change="applyFilters" class="block rounded-md border px-2 py-1.5 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-foreground/60">To</label>
                    <input v-model="dateTo" type="date" @change="applyFilters" class="block rounded-md border px-2 py-1.5 text-sm" />
                </div>
            </div>

            <div class="rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-2 text-left text-foreground/70">Date</th>
                            <th class="p-2 text-left text-foreground/70">Ingredient</th>
                            <th class="p-2 text-right text-foreground/70">Quantity Added</th>
                            <th class="p-2 text-right text-foreground/70">Batch Received</th>
                            <th class="p-2 text-right text-foreground/70">Batch Expiry</th>
                            <th class="p-2 text-left text-foreground/70">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id" class="border-t">
                            <td class="p-2 text-foreground">{{ formatDate(log.created_at) }}</td>
                            <td class="p-2 text-foreground">{{ log.ingredient?.name ?? '—' }}</td>
                            <td class="p-2 text-right text-foreground">
                                +{{ log.quantity_change }} {{ log.ingredient?.unit ?? '' }}
                            </td>
                            <td class="p-2 text-right text-foreground">
                                {{ log.ingredient_batch?.received_date ? formatDate(log.ingredient_batch.received_date) : '—' }}
                            </td>
                            <td class="p-2 text-right text-foreground">
                                {{ log.ingredient_batch?.expiry_date ? formatDate(log.ingredient_batch.expiry_date) : '—' }}
                            </td>
                            <td class="p-2 text-foreground">{{ log.note ?? '—' }}</td>
                        </tr>
                        <tr v-if="!logs.data.length">
                            <td colspan="6" class="p-4 text-center text-foreground/60">No restock history yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-1">
                <button
                    v-for="link in logs.links"
                    :key="link.label"
                    :disabled="!link.url"
                    :class="[
                        'rounded-md border px-2 py-1 text-xs',
                        link.active ? 'bg-primary text-primary-foreground' : '',
                        !link.url ? 'opacity-40' : '',
                    ]"
                    @click="link.url && router.get(link.url, {}, { preserveState: true })"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>