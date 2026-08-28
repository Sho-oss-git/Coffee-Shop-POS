<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Database, Download, HardDriveDownload, LoaderCircle, ShieldAlert, Trash2, Upload, DatabaseBackup } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Backup, BreadcrumbItem } from '@/types';

interface Props {
    backups: Backup[];
    lastBackup?: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System', href: '/backup-restore' },
    { title: 'Backup & Restore', href: '/backup-restore' },
];

const page = usePage<{ flash: { success?: string; error?: string } }>();

const flashMessage = ref<{ type: 'success' | 'error'; text: string } | null>(null);

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            flashMessage.value = { type: 'success', text: flash.success };
        } else if (flash?.error) {
            flashMessage.value = { type: 'error', text: flash.error };
        }
    },
    { immediate: true },
);

// ---------------------------------------------------------------------------
// CREATE BACKUP
// ---------------------------------------------------------------------------

const creating = ref(false);

function createBackup() {
    if (creating.value) return;
    creating.value = true;
    router.post(
        route('backup-restore.create'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                creating.value = false;
            },
        },
    );
}

const downloadHref = computed(() =>
    props.lastBackup ? route('backup-restore.download', { backup: props.lastBackup }) : null,
);

// ---------------------------------------------------------------------------
// DELETE BACKUP
// ---------------------------------------------------------------------------

const deleteTarget = ref<Backup | null>(null);
const deleting = ref(false);

function confirmDelete(backup: Backup) {
    deleteTarget.value = backup;
}

function executeDelete() {
    if (! deleteTarget.value || deleting.value) return;
    deleting.value = true;
    const name = deleteTarget.value.name;
    router.delete(route('backup-restore.destroy', { backup: name }), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            deleteTarget.value = null;
        },
    });
}

// ---------------------------------------------------------------------------
// RESTORE
// ---------------------------------------------------------------------------

const selectedFile = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const showRestoreDialog = ref(false);
const restoreError = ref<string | null>(null);

const form = useForm({
    backup: null as File | null,
});

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    selectedFile.value = target.files && target.files.length > 0 ? target.files[0] : null;
    restoreError.value = null;
}

function openRestoreDialog() {
    if (! selectedFile.value) return;
    restoreError.value = null;
    showRestoreDialog.value = true;
}

function executeRestore() {
    if (! selectedFile.value) return;
    form.backup = selectedFile.value;
    form.post(route('backup-restore.restore'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showRestoreDialog.value = false;
            selectedFile.value = null;
            restoreError.value = null;
            if (fileInput.value) fileInput.value.value = '';
        },
        onError: (errors) => {
            showRestoreDialog.value = false;
            restoreError.value =
                errors.backup ?? 'The backup could not be restored. Please make sure you uploaded a valid JC66 backup file.';
        },
        onFinish: () => {
            form.reset('backup');
        },
    });
}

function formatDate(date: string): string {
    if (! date) return '';
    const d = new Date(date.replace(' ', 'T'));
    if (isNaN(d.getTime())) return date;
    return d.toLocaleString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Backup & Restore" />
        <div class="px-4 py-6">
            <Heading
                title="Backup & Restore"
                description="Protect and restore your system data"
            />

        <!-- Flash banner -->
        <div
            v-if="flashMessage"
            class="mb-6 flex items-start gap-3 rounded-lg border p-4 text-sm"
            :class="
                flashMessage.type === 'success'
                    ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                    : 'border-destructive/40 bg-destructive/10 text-destructive'
            "
        >
            <ShieldAlert v-if="flashMessage.type === 'error'" class="mt-0.5 size-5 shrink-0" />
            <Database v-else class="mt-0.5 size-5 shrink-0" />
            <span class="flex-1">{{ flashMessage.text }}</span>
            <button
                type="button"
                class="text-current/70 hover:text-current"
                @click="flashMessage = null"
                aria-label="Dismiss"
            >
                ✕
            </button>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Create backup -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <DatabaseBackup class="size-5 text-primary" />
                        Database Backup
                    </CardTitle>
                    <CardDescription>
                        Create a complete backup of your system including database data and important
                        uploaded files.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm text-muted-foreground">
                        A single <code class="rounded bg-muted px-1 py-0.5">.zip</code> archive is created
                        containing your database dump and uploaded files. Backups are stored securely on the
                        server and only accessible to administrators.
                    </p>

                    <Button class="w-full sm:w-auto" :disabled="creating" @click="createBackup">
                        <LoaderCircle v-if="creating" class="size-4 animate-spin" />
                        <Database v-else class="size-4" />
                        {{ creating ? 'Creating backup…' : 'Create Backup' }}
                    </Button>

                    <div
                        v-if="downloadHref"
                        class="flex flex-col gap-2 rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm"
                    >
                        <span class="font-medium text-emerald-700 dark:text-emerald-300">
                            Backup ready: {{ lastBackup }}
                        </span>
                        <a
                            :href="downloadHref"
                            class="inline-flex w-fit items-center gap-2 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                        >
                            <Download class="size-4" />
                            Download backup
                        </a>
                    </div>
                </CardContent>
            </Card>

            <!-- Restore -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <HardDriveDownload class="size-5 text-primary" />
                        Restore Database
                    </CardTitle>
                    <CardDescription>
                        Restore your system using a previously created JC66 backup.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-foreground">Backup file</label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".zip,application/zip"
                            :disabled="form.processing"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            @change="onFileChange"
                        />
                        <p v-if="selectedFile" class="text-xs text-muted-foreground">
                            Selected: <span class="font-medium text-foreground">{{ selectedFile.name }}</span>
                        </p>
                        <p v-else class="text-xs text-muted-foreground">No file selected.</p>
                    </div>

                    <p
                        v-if="restoreError"
                        class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        {{ restoreError }}
                    </p>

                    <Button
                        variant="destructive"
                        class="w-full sm:w-auto"
                        :disabled="!selectedFile || form.processing"
                        @click="openRestoreDialog"
                    >
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        <Upload v-else class="size-4" />
                        {{ form.processing ? 'Restoring…' : 'Restore Backup' }}
                    </Button>
                </CardContent>
            </Card>
        </div>

        <!-- Backup history -->
        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Backup History</CardTitle>
                <CardDescription>
                    All backups currently stored on the server, newest first.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    v-if="backups.length === 0"
                    class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    No backups available.<br />
                    Create your first backup to protect your system data.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <th class="px-3 py-2 font-medium">Backup Name</th>
                                <th class="px-3 py-2 font-medium">Date</th>
                                <th class="px-3 py-2 font-medium">Size</th>
                                <th class="px-3 py-2 font-medium">Type</th>
                                <th class="px-3 py-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="backup in backups"
                                :key="backup.name"
                                class="border-b last:border-0 hover:bg-muted/50"
                            >
                                <td class="px-3 py-3 font-medium text-foreground">
                                    {{ backup.name }}
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{ formatDate(backup.date) }}
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{ backup.size_human }}
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="
                                            backup.type === 'pre-restore'
                                                ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                                : 'bg-primary/15 text-primary'
                                        "
                                    >
                                        {{ backup.type === 'pre-restore' ? 'Safety Backup' : 'Full Backup' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a
                                            :href="route('backup-restore.download', { backup: backup.name })"
                                            class="inline-flex items-center gap-1 rounded-md border border-input bg-background px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-accent hover:text-accent-foreground"
                                        >
                                            <Download class="size-3.5" />
                                            Download
                                        </a>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md border border-input bg-background px-2.5 py-1.5 text-xs font-medium text-destructive hover:bg-destructive hover:text-destructive-foreground"
                                            @click="confirmDelete(backup)"
                                        >
                                            <Trash2 class="size-3.5" />
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Restore confirmation dialog -->
        <Dialog v-model:open="showRestoreDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ShieldAlert class="size-5 text-destructive" />
                        Restore Backup?
                    </DialogTitle>
                    <DialogDescription class="space-y-2 text-left">
                        <span class="block font-semibold text-destructive">WARNING:</span>
                        Restoring this backup will replace the current system database and may overwrite
                        current data. Current data may be lost.
                        <span class="mt-2 block">Backup: <span class="font-medium">{{ selectedFile?.name }}</span></span>
                        <span class="block">This action cannot be easily undone.</span>
                        <span class="mt-2 block text-muted-foreground">
                            A safety backup of the current system will be created automatically before
                            restoring.
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button variant="outline" :disabled="form.processing" @click="showRestoreDialog = false">
                        Cancel
                    </Button>
                    <Button variant="destructive" :disabled="form.processing" @click="executeRestore">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        I Understand, Restore Backup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation dialog -->
        <Dialog :open="deleteTarget !== null" @update:open="(v: boolean) => { if (!v) deleteTarget = null; }">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Backup?</DialogTitle>
                    <DialogDescription class="text-left">
                        Are you sure you want to permanently delete:
                        <span class="mt-1 block break-all font-medium text-foreground">
                            {{ deleteTarget?.name }}
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button variant="outline" :disabled="deleting" @click="deleteTarget = null">
                        Cancel
                    </Button>
                    <Button variant="destructive" :disabled="deleting" @click="executeDelete">
                        <LoaderCircle v-if="deleting" class="size-4 animate-spin" />
                        Delete Backup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        </div>
    </AppLayout>
</template>
