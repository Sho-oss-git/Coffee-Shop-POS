<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';

interface Props {
    class?: string;
}

defineProps<Props>();

const page = usePage();
const shopName = (page.props.shop as { name: string })?.name ?? 'JC66 Coffee Shop';
const logoUrl = (page.props.shop as { logo_url: string | null })?.logo_url ?? null;
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
        <img v-if="logoUrl" :src="logoUrl" alt="Shop logo" class="size-full object-contain" />
        <AppLogoIcon v-else class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">{{ shopName }}</span>
    </div>
</template>
