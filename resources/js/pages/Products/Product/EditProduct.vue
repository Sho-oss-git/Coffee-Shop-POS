<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm, usePage } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import ProductFormFields from './ProductFormFields.vue';
import type { Category, Ingredient, Product, RecipeIngredient } from '../types';

const props = defineProps<{
    categories: Category[];
    ingredients: Ingredient[];
}>();

const emit = defineEmits<{ (e: 'manage-categories'): void }>();

const page = usePage<{ flash?: { created_category?: string; renamed_category?: { old: string; new: string } } }>();

const isOpen = ref(false);
const editingId = ref<number | null>(null);
const originalPrice = ref('');
const imagePreview = ref<string | null>(null);
const { isAdmin } = usePermissions();

const form = useForm<{
    name: string;
    category: string;
    price: string;
    image: File | null;
    is_available: boolean;
    tracking_type: 'recipe' | 'finished_stock';
    stock_quantity: string;
    ingredients: RecipeIngredient[];
}>({
    name: '',
    category: '',
    price: '',
    image: null,
    is_available: true,
    tracking_type: 'recipe',
    stock_quantity: '',
    ingredients: [],
});
const priceRequestForm = useForm({ type: 'price_change', reason: '', target_type: 'product', target_id: 0, payload: { new_price: 0 } });

function open(product: Product) {
    editingId.value = product.id;
    originalPrice.value = product.price;
    imagePreview.value = product.image_url;

    form.name = product.name;
    form.category = product.category;
    form.price = product.price;
    form.image = null;
    form.is_available = product.is_available;
    form.tracking_type = product.tracking_type;
    form.stock_quantity = product.stock_quantity != null ? String(product.stock_quantity) : '';
    form.ingredients = product.recipe.map((r) => ({
        ingredient_id: r.ingredient_id,
        quantity: r.quantity,
        unit: r.unit,
    }));
    form.clearErrors();

    isOpen.value = true;
}

function close() {
    isOpen.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.transform((data) => data); // drop any leftover _method transform
    if (imagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = null;
}

function handleImageChange(file: File | null) {
    form.image = file;
    if (file) {
        if (imagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value);
        imagePreview.value = URL.createObjectURL(file);
    }
}

function submit() {
    if (!editingId.value) return;

    if (!isAdmin.value && Number(form.price) !== Number(originalPrice.value)) {
        priceRequestForm.target_id = editingId.value;
        priceRequestForm.payload.new_price = Number(form.price);
        priceRequestForm.reason = `Request changing the price of product "${form.name}" from ₱${Number(originalPrice.value).toFixed(2)} to ₱${Number(form.price).toFixed(2)}.`;
        priceRequestForm.post(route('action-requests.store'), {
            preserveScroll: true,
            onSuccess: () => {
                priceRequestForm.reset();
                close();
            },
        });
        return;
    }

    form.transform((data) => ({ ...data, _method: 'put' })).post(route('products.update', editingId.value), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => close(),
        onError: () => form.transform((data) => data),
    });
}

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.created_category && isOpen.value) form.category = flash.created_category;
        if (flash?.renamed_category && form.category === flash.renamed_category.old) {
            form.category = flash.renamed_category.new;
        }
    },
    { deep: true },
);

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Edit Product</DialogTitle>
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
                        Save Changes
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>