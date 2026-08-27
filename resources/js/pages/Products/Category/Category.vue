<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import CategoryEdit from './CategoryEdit.vue';
import CategoryDelete from './CategoryDelete.vue';
import type { Category as CategoryType } from '../types';

const props = defineProps<{ categories: CategoryType[] }>();

const isOpen = ref(false);
const deleteRef = ref<InstanceType<typeof CategoryDelete> | null>(null);

const form = useForm({ name: '' });

function open() {
    form.reset();
    form.clearErrors();
    isOpen.value = true;
}

function close() {
    isOpen.value = false;
}

function submit() {
    form.post(route('categories.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-h-[85vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Category Management</DialogTitle>
            </DialogHeader>

            <form class="flex items-end gap-2" @submit.prevent="submit">
                <div class="flex-1 space-y-1">
                    <Label for="new-category">Add Category</Label>
                    <Input id="new-category" v-model="form.name" type="text" placeholder="e.g. Cold Drinks" />
                    <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                </div>
                <Button type="submit" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Add
                </Button>
            </form>

            <div class="mt-2 space-y-2 border-t pt-4">
                <Label class="text-xs text-foreground/60">Existing Categories</Label>
                <div class="max-h-72 space-y-2 overflow-auto">
                    <CategoryEdit
                        v-for="cat in props.categories"
                        :key="cat.id"
                        :category="cat"
                        @delete="deleteRef?.open(cat)"
                    />
                    <p v-if="props.categories.length === 0" class="py-4 text-center text-sm text-foreground/60">
                        No categories yet. Add your first one above.
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="close">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <CategoryDelete ref="deleteRef" />
</template>