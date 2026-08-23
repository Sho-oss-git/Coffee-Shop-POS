<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { FormDataConvertible } from '@inertiajs/core';
import { useForm, usePage } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ProductFormFields from './ProductFormFields.vue';
import type { Category, Ingredient, RecipeIngredient } from '../types';

type FormRecipeIngredient = RecipeIngredient & { [key: string]: FormDataConvertible };

const props = defineProps<{
    categories: Category[];
    ingredients: Ingredient[];
}>();

const emit = defineEmits<{ (e: 'manage-categories'): void }>();

const page = usePage<{ flash?: { created_category?: string } }>();

const isOpen = ref(false);
const imagePreview = ref<string | null>(null);

const form = useForm<{
    name: string;
    category: string;
    price: string;
    image: File | null;
    is_available: boolean;
    tracking_type: 'recipe' | 'finished_stock';
    stock_quantity: string;
    cost_price: string;
    ingredients: FormRecipeIngredient[];
}>({
    name: '',
    category: '',
    price: '',
    image: null,
    is_available: true,
    tracking_type: 'recipe',
    stock_quantity: '',
    cost_price: '',
    ingredients: [],
});

function open() {
    if (imagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = null;

    form.reset();
    form.clearErrors();
    form.is_available = true;
    form.tracking_type = 'recipe';
    form.ingredients = [];
    form.category = props.categories[0]?.name ?? '';

    isOpen.value = true;
}

function close() {
    isOpen.value = false;
    form.reset();
    form.clearErrors();
    if (imagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = null;
}

function handleImageChange(file: File | null) {
    if (imagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value);

    form.image = file;
    imagePreview.value = file ? URL.createObjectURL(file) : null;
}

function submit() {
    form.post(route('products.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => close(),
    });
}

// A category created via the category manager while this dialog is open
// becomes the selected category.
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.created_category && isOpen.value) form.category = flash.created_category;
    },
    { deep: true },
);

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Add Product</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <ProductFormFields
                    :form="form"
                    :categories="props.categories"
                    :ingredients="props.ingredients"
                    :image-preview="imagePreview"
                    @change-image="handleImageChange"
                    @manage-categories="emit('manage-categories')"
                />

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Add Product
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>