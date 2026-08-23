<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'User Management', href: '/user-management' },
    { title: 'Add Employee', href: '/user-management/create' },
];

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'manager' as 'manager' | 'cashier',
});

function submit() {
    form.post('/user-management');
}
</script>

<template>
    <Head title="Add Employee" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 max-w-lg">
            <div>
                <h1 class="text-2xl font-semibold">Add Employee</h1>
                <p class="text-sm text-muted-foreground">Create a new Manager or Cashier account.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="name">Full Name</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="email">Email</Label>
                    <Input id="email" type="email" v-model="form.email" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="space-y-2">
                    <Label for="password">Password</Label>
                    <Input id="password" type="password" v-model="form.password" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="space-y-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <Input id="password_confirmation" type="password" v-model="form.password_confirmation" />
                </div>

                <div class="space-y-2">
                    <Label for="role">Role</Label>
                    <select id="role" v-model="form.role" class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                        <option value="manager">Manager</option>
                        <option value="cashier">Cashier</option>
                    </select>
                    <InputError :message="form.errors.role" />
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">Add Employee</Button>
                    <Link href="/user-management">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>