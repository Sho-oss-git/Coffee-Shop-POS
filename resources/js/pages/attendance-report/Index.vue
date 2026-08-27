<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type AttendanceRow = {
    date: string;
    day_label: string;
    is_day_off: boolean;
    expected_time_in: string | null;
    expected_time_out: string | null;
    actual_time_in: string | null;
    actual_time_out: string | null;
    late_minutes: number | null;
    undertime_minutes: number | null;
    overtime_minutes: number | null;
    break_minutes: number | null;
    total_work_hours: number | null;
    status: string;
};

const props = defineProps<{
    employees: Array<{ id: number; name: string }>;
    selectedEmployeeId: number | null;
    startDate: string;
    endDate: string;
    rows: AttendanceRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Attendance Report', href: '/reports/attendance' }];

const employeeId = ref(props.selectedEmployeeId);
const startDate = ref(props.startDate);
const endDate = ref(props.endDate);

watch([employeeId, startDate, endDate], ([emp, start, end]) => {
    router.get(
        '/reports/attendance',
        { employee_id: emp, start_date: start, end_date: end },
        { preserveState: true, replace: true },
    );
});

function statusClass(status: string) {
    return {
        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': status === 'Complete',
        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': status === 'Still Working',
        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': status === 'Absent',
        'bg-muted text-muted-foreground': ['Day Off', 'No Schedule', 'No Record'].includes(status),
    };
}

function formatMinutes(minutes: number | null) {
    if (minutes === null) return '—';
    const totalMinutes = Math.round(minutes);
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function formatHours(hours: number | null) {
    if (hours === null) return '—';
    const totalMinutes = Math.round(hours * 60);
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}
</script>

<template>
    <Head title="Attendance Report" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-col gap-6 p-3 sm:p-4">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Attendance Report</h1>
                <p class="text-sm text-foreground/60">Expected vs actual time in/out, with Late, Undertime, Overtime, and Break totals.</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <select v-model.number="employeeId" class="h-10 w-full rounded-md border bg-background px-3 text-sm text-foreground sm:w-56">
                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
                </select>
                <input v-model="startDate" type="date" class="h-10 w-full rounded-md border bg-background px-3 text-sm text-foreground sm:w-auto" />
                <span class="text-sm text-foreground/60">to</span>
                <input v-model="endDate" type="date" class="h-10 w-full rounded-md border bg-background px-3 text-sm text-foreground sm:w-auto" />
            </div>

            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-sm">
                            <thead>
                                <tr class="border-b bg-muted/40 text-left">
                                    <th class="p-3 font-medium text-foreground/70">Date</th>
                                    <th class="p-3 font-medium text-foreground/70">Expected</th>
                                    <th class="p-3 font-medium text-foreground/70">Actual</th>
                                    <th class="p-3 font-medium text-foreground/70">Late</th>
                                    <th class="p-3 font-medium text-foreground/70">Undertime</th>
                                    <th class="p-3 font-medium text-foreground/70">Overtime</th>
                                    <th class="p-3 font-medium text-foreground/70">Break</th>
                                    <th class="p-3 font-medium text-foreground/70">Total Hours</th>
                                    <th class="p-3 font-medium text-foreground/70">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rows" :key="row.date" class="border-b last:border-0">
                                    <td class="p-3 whitespace-nowrap text-foreground">{{ row.day_label }}</td>
                                    <td class="p-3 whitespace-nowrap text-xs text-foreground/60">
                                        <template v-if="row.expected_time_in">
                                            {{ row.expected_time_in }} - {{ row.expected_time_out ?? '—' }}
                                        </template>
                                        <template v-else>&mdash;</template>
                                    </td>
                                    <td class="p-3 whitespace-nowrap text-xs text-foreground/60">
                                        <template v-if="row.actual_time_in">
                                            {{ row.actual_time_in }} - {{ row.actual_time_out ?? 'Ongoing' }}
                                        </template>
                                        <template v-else>&mdash;</template>
                                    </td>
                                    <td class="p-3" :class="row.late_minutes ? 'font-medium text-red-600' : 'text-foreground/60'">
                                        {{ formatMinutes(row.late_minutes) }}
                                    </td>
                                    <td class="p-3" :class="row.undertime_minutes ? 'font-medium text-amber-600' : 'text-foreground/60'">
                                        {{ formatMinutes(row.undertime_minutes) }}
                                    </td>
                                    <td class="p-3" :class="row.overtime_minutes ? 'font-medium text-green-600' : 'text-foreground/60'">
                                        {{ formatMinutes(row.overtime_minutes) }}
                                    </td>
                                    <td class="p-3 text-foreground/60">{{ formatMinutes(row.break_minutes) }}</td>
                                    <td class="p-3 font-medium text-foreground">
                                        {{ formatHours(row.total_work_hours) }}
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">
                                            {{ row.status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="rows.length === 0">
                                    <td colspan="9" class="p-8 text-center text-foreground/60">Walang employee na napili.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>