<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ImageOff, Loader2, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AddProduct from './Product/AddProduct.vue';
import EditProduct from './Product/EditProduct.vue';
import DeleteProduct from './Product/DeleteProduct.vue';
import Category from './Category/Category.vue';
import type { Category as CategoryType, Ingredient, Product } from './types';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps<{
    products: Product[];
    categories: CategoryType[];
    ingredients: Ingredient[];
    filters: { search?: string; category?: string; status?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Products', href: '/products' }];
const { isAdmin } = usePermissions();

/* Notifications */
const page = usePage<{ flash?: { success?: string; error?: string } }>();
const notification = ref<{ type: 'success' | 'error'; message: string } | null>(null);
let notificationTimeout: ReturnType<typeof setTimeout> | null = null;

function showNotification(type: 'success' | 'error', message: string) {
    notification.value = { type, message };
    if (notificationTimeout) clearTimeout(notificationTimeout);
    notificationTimeout = setTimeout(() => (notification.value = null), 3500);
}

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) showNotification('success', flash.success);
        if (flash?.error) showNotification('error', flash.error);
    },
    { deep: true },
);

/* Search / Filters */
const search = ref(props.filters.search ?? '');
const categoryFilter = ref(props.filters.category ?? '');
const statusFilter = ref(props.filters.status ?? '');
const isFiltering = ref(false);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    isFiltering.value = true;
    router.get(
        route('products.index'),
        {
            search: search.value || undefined,
            category: categoryFilter.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (isFiltering.value = false) },
    );
}

watch(search, () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 350);
});
watch([categoryFilter, statusFilter], applyFilters);

const hasActiveFilters = computed(() => !!(search.value || categoryFilter.value || statusFilter.value));

function clearFilters() {
    if (searchDebounce) clearTimeout(searchDebounce);
    search.value = '';
    categoryFilter.value = '';
    statusFilter.value = '';
}

/* Dialog refs */
const addProductRef = ref<InstanceType<typeof AddProduct> | null>(null);
const editProductRef = ref<InstanceType<typeof EditProduct> | null>(null);
const deleteProductRef = ref<InstanceType<typeof DeleteProduct> | null>(null);
const categoryRef = ref<InstanceType<typeof Category> | null>(null);

function recipeSummary(product: Product): string {
    if (product.tracking_type === 'finished_stock') return `${product.stock_quantity ?? 0} pcs in stock`;
    if (product.recipe.length === 0) return 'No recipe set';
    return product.recipe.map((r) => `${r.ingredient.name} ${r.quantity}${r.unit}`).join(', ');
}

/*
 * Group products by category, with a trailing "Out of stock" section.
 *
 * Mirrors Product::isAvailable() on the backend: a recipe-tracked product is
 * out of stock if ANY ingredient its recipe needs has zero stock left, not
 * just when the manual is_available flag is off or (for finished-stock
 * items) stock_quantity hits zero.
 */
function isOutOfStock(product: Product): boolean {
    if (!product.is_available) return true;

    if (product.tracking_type === 'finished_stock') {
        return (product.stock_quantity ?? 0) <= 0;
    }

    return product.recipe.some((line) => (line.ingredient?.total_stock ?? 0) <= 0);
}

interface ProductSection {
    key: string;
    label: string;
    products: Product[];
}

const sections = computed<ProductSection[]>(() => {
    const inStock = props.products.filter((p) => !isOutOfStock(p));
    const outOfStock = props.products.filter((p) => isOutOfStock(p));

    const byCategory = new Map<string, Product[]>();
    for (const product of inStock) {
        const list = byCategory.get(product.category) ?? [];
        list.push(product);
        byCategory.set(product.category, list);
    }

    const result: ProductSection[] = Array.from(byCategory.entries())
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([category, list]) => ({ key: category, label: category, products: list }));

    if (outOfStock.length > 0) {
        result.push({ key: '__out_of_stock', label: 'Out of stock', products: outOfStock });
    }

    return result;
});

function categoryInitial(name: string): string {
    return name.trim().charAt(0).toUpperCase() || '?';
}
</script>

<template>
    <Head title="Products" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="notification"
                class="fixed right-4 top-4 z-50 max-w-[calc(100vw-2rem)] rounded-lg border px-4 py-3 text-sm shadow-lg"
                :class="
                    notification.type === 'success'
                        ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300'
                        : 'border-destructive/30 bg-destructive/10 text-destructive'
                "
            >
                {{ notification.message }}
            </div>
        </Transition>

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-xl font-semibold text-foreground">Products</h1>
                <Button class="w-full sm:w-auto" @click="addProductRef?.open()">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Product
                </Button>
            </div>

            <!-- Search / Filters -->
            <div class="flex flex-col gap-3">
                <div class="relative w-full sm:max-w-xs">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground/60" />
                    <Input v-model="search" type="text" placeholder="Search products..." class="w-full pl-9" />
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <select
                        v-model="categoryFilter"
                        class="h-9 min-w-[140px] flex-1 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring sm:flex-none"
                    >
                        <option value="">All Categories</option>
                        <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
                    </select>

                    <select
                        v-model="statusFilter"
                        class="h-9 min-w-[140px] flex-1 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring sm:flex-none"
                    >
                        <option value="">All Statuses</option>
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                    </select>

                    <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">Clear filters</Button>
                    <Loader2 v-if="isFiltering" class="h-4 w-4 animate-spin text-muted-foreground" />
                </div>
            </div>

            <!-- Product Listing: neutral container, cards keep their own theme -->
            <div class="relative flex-1 overflow-auto rounded-2xl border border-sidebar-border/70 p-3 dark:border-sidebar-border sm:p-5">
                <div v-if="props.products.length > 0" class="flex flex-col gap-6 sm:gap-8">
                    <section v-for="section in sections" :key="section.key">
                        <span
                            class="mb-3 inline-block rounded-full px-4 py-1.5 text-sm font-medium"
                            :class="
                                section.key === '__out_of_stock'
                                    ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'
                                    : 'bg-muted text-foreground'
                            "
                        >
                            {{ section.label }}
                        </span>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                            <div
                                v-for="product in section.products"
                                :key="product.id"
                                class="flex flex-col gap-2 rounded-2xl border border-[#2a5049] bg-[#173832] p-2 shadow-lg shadow-black/20 transition-colors hover:border-[#3d6b62] sm:gap-3 sm:p-3"
                            >
                                <!-- Image -->
                                <div class="relative aspect-square overflow-hidden rounded-xl bg-[#0d2b25]">
                                    <img
                                        v-if="product.image_url"
                                        :src="product.image_url"
                                        :alt="product.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full w-full items-center justify-center">
                                        <ImageOff class="h-6 w-6 text-[#3d6b62] sm:h-8 sm:w-8" />
                                    </div>

                                    <!-- Category badge -->
                                    <div
                                        class="absolute left-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-[#0d2b25]/80 text-[10px] font-semibold text-[#d8a851] ring-1 ring-[#d8a851]/60 backdrop-blur sm:left-2 sm:top-2 sm:h-7 sm:w-7 sm:text-xs"
                                        :title="product.category"
                                    >
                                        {{ categoryInitial(product.category) }}
                                    </div>

                                    <!-- Edit / Delete -->
                                    <div class="absolute right-1.5 top-1.5 flex gap-1 sm:right-2 sm:top-2 sm:gap-1.5">
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-[#0d2b25]/80 text-[#d8a851] backdrop-blur transition-colors hover:bg-[#0d2b25] hover:text-[#e9c179] sm:h-7 sm:w-7"
                                            title="Edit product"
                                            @click="editProductRef?.open(product)"
                                        >
                                            <Pencil class="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-[#0d2b25]/80 text-[#d8a851] backdrop-blur transition-colors hover:bg-[#0d2b25] hover:text-[#e9c179] sm:h-7 sm:w-7"
                                            :title="isAdmin ? 'Delete product' : 'Request product deletion'"
                                            @click="deleteProductRef?.open(product)"
                                        >
                                            <Trash2 class="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Details -->
                                <div class="flex flex-col gap-0.5">
                                    <h3 class="truncate text-xs font-semibold text-[#f5efe0] sm:text-sm" :title="product.name">
                                        {{ product.name }}
                                    </h3>
                                    <p class="text-xs font-bold text-[#d8a851] sm:text-sm">
                                        ₱{{ Number(product.price).toFixed(2) }}
                                    </p>
                                    <p class="truncate text-[11px] text-[#9db8ae] sm:text-xs" :title="recipeSummary(product)">
                                        {{ recipeSummary(product) }}
                                    </p>
                                    <p class="mt-1 flex items-center gap-1.5 text-[11px] sm:text-xs">
                                        <span
                                            class="h-1.5 w-1.5 shrink-0 rounded-full"
                                            :class="!isOutOfStock(product) ? 'bg-emerald-400' : 'bg-[#9db8ae]'"
                                        />
                                        <span :class="!isOutOfStock(product) ? 'text-emerald-300' : 'text-[#9db8ae]'">
                                            {{ !isOutOfStock(product) ? 'Available' : 'Unavailable' }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Empty State -->
                <div v-else class="flex min-h-[250px] flex-col items-center justify-center gap-3 px-4 text-center text-foreground/60">
                    <ImageOff class="h-10 w-10" />
                    <p v-if="hasActiveFilters">No products match your filters.</p>
                    <p v-else>No products yet. Add your first product to get started.</p>
                    <Button v-if="!hasActiveFilters" size="sm" @click="addProductRef?.open()">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Product
                    </Button>
                </div>
            </div>
        </div>

        <AddProduct
            ref="addProductRef"
            :categories="props.categories"
            :ingredients="props.ingredients"
            @manage-categories="categoryRef?.open()"
        />
        <EditProduct
            ref="editProductRef"
            :categories="props.categories"
            :ingredients="props.ingredients"
            @manage-categories="categoryRef?.open()"
        />
        <DeleteProduct ref="deleteProductRef" />
        <Category ref="categoryRef" :categories="props.categories" />
    </AppLayout>
</template>