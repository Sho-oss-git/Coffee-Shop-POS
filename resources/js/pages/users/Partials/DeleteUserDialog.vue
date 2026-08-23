<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { User } from '@/types';

const props = defineProps<{ open: boolean; user?: User | null }>();
const emit = defineEmits<{ 'update:open': [boolean] }>();

function confirmDelete() {
    if (!props.user) return;
    router.delete(`/user-management/${props.user.id}`, { onSuccess: () => emit('update:open', false) });
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" role="dialog" aria-modal="true">
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
            <h2 class="text-lg font-semibold">Delete User?</h2>
            <p class="mt-2 text-sm text-gray-600">
                Are you sure you want to delete {{ user?.name }}? This action cannot be undone.
            </p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="rounded-md border px-4 py-2" @click="emit('update:open', false)">
                    Cancel
                </button>
                <button type="button" class="rounded-md bg-red-600 px-4 py-2 text-white" @click="confirmDelete">
                    Delete
                </button>
            </div>
        </div>
    </div>
</template>