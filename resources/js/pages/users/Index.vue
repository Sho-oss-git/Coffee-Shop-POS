<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Users, CircleCheck, Clock, History, MoreVertical, Search } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

// TODO(backend): add `position` (job title) column to the employees table/model.
// Until then it's optional and falls back to `role` below.
type EmployeeRow = {
    id: number | string;
    name: string;
    email: string;
    role?: string;
    position?: string;
    status?: 'working' | 'break' | 'off_duty';
    // Formatted strings (e.g. "02:15 PM") from the user's latest BreakLog, or null if none yet.
    break_started_at?: string | null;
    break_ended_at?: string | null;
};

const props = defineProps<{
    users: { data: EmployeeRow[]; links: any[]; current_page: number; last_page: number };
    filters: { search?: string; position?: string; status?: string };
    // TODO(backend): pass real aggregate counts from the controller instead of
    // deriving from the current page only (see note below the component).
    stats?: { total: number; working: number; on_break: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'User Management', href: '/user-management' }];

const search = ref(props.filters.search ?? '');
const positionFilter = ref(props.filters.position ?? 'all');
const statusFilter = ref(props.filters.status ?? 'all');

let searchTimeout: ReturnType<typeof setTimeout> | undefined;
watch([search, positionFilter, statusFilter], ([s, p, st]: [string, string, string]) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(
        () => router.get('/user-management', { search: s, position: p, status: st }, { preserveState: true, replace: true }),
        300,
    );
});

const stats = computed(
    () =>
        props.stats ?? {
            total: props.users.data.length,
            working: props.users.data.filter((e) => e.status === 'working').length,
            on_break: props.users.data.filter((e) => e.status === 'break').length,
        },
);

function initials(name: string) {
    return name
        .split(' ')
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function confirmDelete(employee: EmployeeRow) {
    if (window.confirm(`Delete Employee?\n\nAre you sure you want to delete ${employee.name}? This action cannot be undone.`)) {
        router.delete(`/user-management/${employee.id}`);
    }
}

// --- Activity log modal (login, break start/end, logout) ---
type ActivityLog = {
    id: number;
    action: 'login' | 'logout' | 'break_start' | 'break_end';
    logged_at: string;
};

const actionLabels: Record<ActivityLog['action'], string> = {
    login: 'Logged In',
    logout: 'Logged Out',
    break_start: 'Break Started',
    break_end: 'Break Ended',
};

const actionColors: Record<ActivityLog['action'], string> = {
    login: 'text-green-600',
    logout: 'text-muted-foreground',
    break_start: 'text-amber-600',
    break_end: 'text-blue-600',
};

const isRecordsOpen = ref(false);
const selectedEmployeeName = ref('');
const activityLogs = ref<ActivityLog[]>([]);
const loadingRecords = ref(false);

async function viewRecords(employee: EmployeeRow) {
    selectedEmployeeName.value = employee.name;
    isRecordsOpen.value = true;
    loadingRecords.value = true;
    activityLogs.value = [];

    try {
        const res = await fetch(`/user-management/${employee.id}/logs`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        activityLogs.value = data.logs ?? [];
    } catch (e) {
        console.error('Failed to load activity logs', e);
    } finally {
        loadingRecords.value = false;
    }
}
</script>

<template>
    <Head title="User Management" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-col gap-6 p-3 sm:p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Employees</h1>
                    <p class="text-sm text-muted-foreground">Manage employees, positions, and shift status.</p>
                </div>
                <Button type="button" class="w-full sm:w-auto" @click="router.visit('/user-management/create')">+ Add Employee</Button>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="rounded-full bg-muted p-2"><Users class="h-5 w-5 text-muted-foreground" /></div>
                        <div>
                            <p class="text-xs text-muted-foreground">Employees</p>
                            <p class="text-xl font-semibold">{{ stats.total }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="rounded-full bg-green-100 p-2 dark:bg-green-900/30"><CircleCheck class="h-5 w-5 text-green-600" /></div>
                        <div>
                            <p class="text-xs text-muted-foreground">Working</p>
                            <p class="text-xl font-semibold">{{ stats.working }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="rounded-full bg-amber-100 p-2 dark:bg-amber-900/30"><Clock class="h-5 w-5 text-amber-600" /></div>
                        <div>
                            <p class="text-xs text-muted-foreground">On Break</p>
                            <p class="text-xl font-semibold">{{ stats.on_break }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative sm:max-w-xs sm:flex-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search employee..." class="w-full pl-9" />
                </div>
                <select v-model="positionFilter" class="h-10 rounded-md border bg-background px-3 text-sm sm:w-44">
                    <option value="all">All Positions</option>
                    <option value="barista">Barista</option>
                    <option value="cashier">Cashier</option>
                    <option value="supervisor">Supervisor</option>
                </select>
                <select v-model="statusFilter" class="h-10 rounded-md border bg-background px-3 text-sm sm:w-44">
                    <option value="all">All Status</option>
                    <option value="working">Working</option>
                    <option value="break">On Break</option>
                    <option value="off_duty">Off Duty</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="w-full min-w-[680px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40 text-left">
                            <th class="p-3 font-medium">Employee</th>
                            <th class="p-3 font-medium">Position</th>
                            <th class="p-3 font-medium">Status</th>
                            <th class="p-3 font-medium">Last Break</th>
                            <th class="p-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="employee in props.users.data" :key="employee.id" class="border-b last:border-0">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                        {{ initials(employee.name) }}
                                    </div>
                                    <div>
                                        <p class="font-medium leading-none">{{ employee.name }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">{{ employee.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 capitalize">{{ employee.position ?? employee.role }}</td>
                            <td class="p-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': employee.status === 'working',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': employee.status === 'break',
                                        'bg-muted text-muted-foreground': !employee.status || employee.status === 'off_duty',
                                    }"
                                >
                                    {{ employee.status === 'working' ? 'Working' : employee.status === 'break' ? 'On Break' : 'Off Duty' }}
                                </span>
                            </td>
                            <td class="p-3 text-xs text-muted-foreground">
                                <div class="flex items-center gap-2">
                                    <div>
                                        <template v-if="employee.break_started_at">
                                            <div>In: {{ employee.break_started_at }}</div>
                                            <div v-if="employee.break_ended_at">Out: {{ employee.break_ended_at }}</div>
                                            <div v-else class="font-medium text-amber-600">Still on break</div>
                                        </template>
                                        <template v-else>&mdash;</template>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7 shrink-0"
                                        title="View break records"
                                        @click="viewRecords(employee)"
                                    >
                                        <History class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </td>
                            <td class="p-3 text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" type="button">
                                            <MoreVertical class="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @click="router.visit(`/user-management/${employee.id}/edit`)">Edit</DropdownMenuItem>
                                        <DropdownMenuItem @click="router.visit(`/user-management/${employee.id}/schedule`)">Set Schedule</DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem class="text-destructive" @click="confirmDelete(employee)">Delete</DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                        <tr v-if="props.users.data.length === 0">
                            <td colspan="5" class="p-8 text-center text-muted-foreground">No employees found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="isRecordsOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Activity Log</DialogTitle>
                    <DialogDescription>{{ selectedEmployeeName }}'s login, break, and logout history.</DialogDescription>
                </DialogHeader>

                <div class="max-h-96 overflow-y-auto">
                    <p v-if="loadingRecords" class="py-6 text-center text-sm text-muted-foreground">Loading records...</p>

                    <p v-else-if="activityLogs.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        No activity recorded yet.
                    </p>

                    <ul v-else class="divide-y">
                        <li v-for="log in activityLogs" :key="log.id" class="flex items-center justify-between py-2.5 text-sm">
                            <span class="font-medium" :class="actionColors[log.action]">
                                {{ actionLabels[log.action] }}
                            </span>
                            <span class="text-xs text-muted-foreground">{{ log.logged_at }}</span>
                        </li>
                    </ul>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>