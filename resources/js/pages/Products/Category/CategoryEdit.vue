<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { Loader2, Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import type { Category } from '../types';

const props = defineProps<{ category: Category }>();
const emit = defineEmits<{ (e: 'delete'): void }>();

const isEditing = ref(false);
const form = useForm({ name: props.category.name });

function start() {
    isEditing.value = true;
    form.reset();
    form.clearErrors();
    form.name = props.category.name;
}

function cancel() {
    isEditing.value = false;
    form.reset();
    form.clearErrors();
}

function submit() {
    form.put(route('categories.update', String(props.category.id)), {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
        },
    });
}
</script>

<template>
    <div class="flex items-center justify-between gap-2 rounded-md border border-input px-3 py-2">
        <template v-if="isEditing">
            <div class="flex-1">
                <Input v-model="form.name" type="text" class="h-8 w-full" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="flex shrink-0 gap-1">
                <Button type="button" size="sm" :disabled="form.processing" @click="submit">
                    <Loader2 v-if="form.processing" class="mr-1 h-3.5 w-3.5 animate-spin" />
                    Save
                </Button>
                <Button type="button" size="sm" variant="outline" @click="cancel">Cancel</Button>
            </div>
        </template>
        <template v-else>
            <span class="truncate text-sm">{{ category.name }}</span>
            <div class="flex shrink-0 gap-1">
                <Button type="button" size="sm" variant="outline" @click="start">
                    <Pencil class="mr-1 h-3.5 w-3.5" />
                    Edit
                </Button>
                <Button type="button" size="sm" variant="outline" @click="emit('delete')">
                    <Trash2 class="mr-1 h-3.5 w-3.5" />
                    Delete
                </Button>
            </div>
        </template>
    </div>
</template>