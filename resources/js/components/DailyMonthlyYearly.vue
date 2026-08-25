<script setup lang="ts">
import { computed } from 'vue';

type Period = 'daily' | 'monthly' | 'yearly';

const props = defineProps<{
    period: Period;
    date: string;
}>();

const emit = defineEmits<{
    'update:period': [Period];
    'update:date': [string];
    /** Fires after either period or date changes, so the parent can reload/refetch. */
    change: [];
}>();

const PERIODS = ['daily', 'monthly', 'yearly'] as const;

function setPeriod(p: Period) {
    if (p === props.period) return;
    emit('update:period', p);
    emit('change');
}

function onDateChange(e: Event) {
    const value = (e.target as HTMLInputElement).value;
    emit('update:date', value);
    emit('change');
}

// Daily uses a day picker, monthly a month picker, yearly a plain year number field.
const inputType = computed(() => (props.period === 'yearly' ? 'number' : props.period === 'monthly' ? 'month' : 'date'));
</script>

<template>
    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
        <div class="flex overflow-hidden rounded-md border">
            <button
                v-for="p in PERIODS"
                :key="p"
                type="button"
                @click="setPeriod(p)"
                class="px-3 py-1.5 text-sm capitalize"
                :class="period === p ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted'"
            >
                {{ p }}
            </button>
        </div>

        <input
            :value="date"
            :type="inputType"
            @change="onDateChange"
            class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground dark:[color-scheme:dark] sm:w-auto"
        />
    </div>
</template>