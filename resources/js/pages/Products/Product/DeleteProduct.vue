<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router, useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import type { Product } from '../types';

const target = ref<Product | null>(null);
const isDeleting = ref(false);
const requestForm = useForm({ type: 'product_deletion', reason: '', target_type: 'product', target_id: 0 });
const { isAdmin } = usePermissions();

function open(product: Product) {
    target.value = product;
}

function close() {
    target.value = null;
}

function destroy() {
    if (!target.value) return;

    if (!isAdmin.value) {
        requestForm.reason = `Request deletion of product "${target.value.name}".`;
        requestForm.target_id = target.value.id;
        requestForm.post(route('action-requests.store'), {
            preserveScroll: true,
            onFinish: () => {
                requestForm.reset();
                target.value = null;
            },
        });
        return;
    }

    isDeleting.value = true;
    router.delete(route('products.destroy', String(target.value.id)), {
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
                <DialogTitle>{{ isAdmin ? 'Delete Product' : 'Request Product Deletion' }}</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                <template v-if="isAdmin">
                    Are you sure you want to delete "{{ target?.name }}"? This cannot be undone.
                </template>
                <template v-else>
                    Submit a deletion request for "{{ target?.name }}" to an Admin for approval?
                </template>
            </p>
            <DialogFooter>
                <Button variant="outline" @click="close">Cancel</Button>
                <Button variant="destructive" :disabled="isDeleting || requestForm.processing" @click="destroy">
                    <Loader2 v-if="isDeleting || requestForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                    {{ isAdmin ? 'Delete' : 'Send Request' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>