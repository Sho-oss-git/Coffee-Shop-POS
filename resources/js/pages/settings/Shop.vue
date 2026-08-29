<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';

interface Props {
    shop: {
        shop_name: string;
        logo_url: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Shop settings',
        href: '/settings/shop',
    },
];

const page = usePage<SharedData>();
const logoInput = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(props.shop.logo_url);

const form = useForm({
    _method: 'PATCH',
    shop_name: props.shop.shop_name,
    logo: null as File | null,
    remove_logo: false,
});

function onLogoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (file) {
        form.logo = file;
        form.remove_logo = false;
        preview.value = URL.createObjectURL(file);
    }
}

function removeLogo() {
    form.logo = null;
    form.remove_logo = true;
    preview.value = null;

    if (logoInput.value) {
        logoInput.value.value = '';
    }
}

function submit() {
    form.post(route('shop.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.logo = null;
            if (logoInput.value) {
                logoInput.value.value = '';
            }
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Shop settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Shop branding" description="Upload your shop logo and set the name shown across the system." />

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Logo upload -->
                    <div class="grid gap-2">
                        <Label for="logo">Shop Logo</Label>

                        <div class="flex items-center gap-4">
                            <div class="flex size-20 items-center justify-center overflow-hidden rounded-md border bg-muted">
                                <img v-if="preview" :src="preview" alt="Shop logo preview" class="size-full object-contain" />
                                <span v-else class="text-xs text-muted-foreground">No logo</span>
                            </div>

                            <div class="flex flex-col gap-2">
                                <Input id="logo" type="file" accept="image/*" class="mt-1 block w-full" @input="onLogoChange" ref="logoInput" />
                                <Button v-if="preview" type="button" variant="outline" size="sm" class="w-fit" @click="removeLogo">
                                    Remove logo
                                </Button>
                            </div>
                        </div>

                        <p class="text-xs text-foreground/60">Accepted formats: JPG, PNG, SVG, WEBP. Max 2 MB.</p>
                        <InputError class="mt-2" :message="form.errors.logo ?? form.errors.remove_logo" />
                    </div>

                    <!-- Shop name -->
                    <div class="grid gap-2">
                        <Label for="shop_name">Shop Name</Label>
                        <Input id="shop_name" class="mt-1 block w-full" v-model="form.shop_name" required maxlength="255" placeholder="JC66 Coffee Shop" />
                        <InputError class="mt-2" :message="form.errors.shop_name" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>

                        <span v-if="page.props.flash?.success" class="text-sm text-green-600">
                            {{ page.props.flash.success }}
                        </span>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
