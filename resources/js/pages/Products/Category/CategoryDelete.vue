<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import type { Category } from '../types';

const target = ref<Category | null>(null);
const isDeleting = ref(false);

function open(category: Category) {
    target.value = category;
}

function close() {
    target.value = null;
}

function destroy() {
    if (!target.value) return;
    isDeleting.value = true;
    router.delete(route('categories.destroy', String(target.value.id)), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            target.value = null;
        },
    });
}

defineExpose({ open });
</script>

<template>
    <Dialog :open="!!target" @update:open="(val) => !val && close()">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete Category</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Are you sure you want to delete "{{ target?.name }}"? This cannot be undone.
            </p>
            <DialogFooter>
                <Button variant="outline" @click="close">Cancel</Button>
                <Button variant="destructive" :disabled="isDeleting" @click="destroy">
                    <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>