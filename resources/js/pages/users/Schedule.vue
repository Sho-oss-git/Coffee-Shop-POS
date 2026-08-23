<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type ScheduleDay = {
    day_of_week: number;
    day_label: string;
    expected_time_in: string | null;
    expected_time_out: string | null;
    is_day_off: boolean;
};

const props = defineProps<{
    employee: { id: number; name: string };
    schedule: ScheduleDay[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'User Management', href: '/user-management' },
    { title: `${props.employee.name}'s Schedule`, href: '#' },
];

const form = ref<ScheduleDay[]>(props.schedule.map((d) => ({ ...d })));
const processing = ref(false);

function toggleDayOff(day: ScheduleDay) {
    day.is_day_off = !day.is_day_off;
    if (day.is_day_off) {
        day.expected_time_in = null;
        day.expected_time_out = null;
    }
}

function submit() {
    processing.value = true;
    router.put(
        `/user-management/${props.employee.id}/schedule`,
        { schedule: form.value },
        {
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Head :title="`${employee.name}'s Schedule`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ employee.name }}'s Weekly Schedule</h1>
                <p class="text-sm text-muted-foreground">Set expected time in/out per day, or mark a day off.</p>
            </div>

            <Card>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40 text-left">
                                <th class="p-3 font-medium">Day</th>
                                <th class="p-3 font-medium">Expected Time In</th>
                                <th class="p-3 font-medium">Expected Time Out</th>
                                <th class="p-3 font-medium">Day Off</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="day in form" :key="day.day_of_week" class="border-b last:border-0">
                                <td class="p-3 font-medium">{{ day.day_label }}</td>
                                <td class="p-3">
                                    <Input
                                        v-model="day.expected_time_in"
                                        type="time"
                                        class="w-36"
                                        :disabled="day.is_day_off"
                                    />
                                </td>
                                <td class="p-3">
                                    <Input
                                        v-model="day.expected_time_out"
                                        type="time"
                                        class="w-36"
                                        :disabled="day.is_day_off"
                                    />
                                </td>
                                <td class="p-3">
                                    <button
                                        type="button"
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                                        :class="
                                            day.is_day_off
                                                ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                        @click="toggleDayOff(day)"
                                    >
                                        {{ day.is_day_off ? 'Day Off' : 'Working Day' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" @click="router.visit('/user-management')">Cancel</Button>
                <Button type="button" :disabled="processing" @click="submit">
                    {{ processing ? 'Saving...' : 'Save Schedule' }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>