<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import type { Employee } from '@/types';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    user?: Employee | null;
}>();

const emit = defineEmits<{ 'update:open': [boolean] }>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'cashier' as 'manager' | 'cashier',
});

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        if (props.mode === 'edit' && props.user) {
            form.reset();
            form.name = props.user.name;
            form.email = props.user.email;
            form.role = props.user.role;
        } else {
            form.reset();
            form.role = 'cashier';
        }
        form.clearErrors();
    }
});

function submit() {
    if (props.mode === 'create') {
        form.post('/user-management', { onSuccess: () => emit('update:open', false) });
    } else if (props.user) {
        form.put(`/user-management/${props.user.id}`, { onSuccess: () => emit('update:open', false) });
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ mode === 'create' ? 'Add Employee' : 'Edit Employee' }}</DialogTitle>
            </DialogHeader>

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
                    <Label for="password">
                        Password <span v-if="mode === 'edit'" class="text-muted-foreground">(leave blank to keep current)</span>
                    </Label>
                    <Input id="password" type="password" v-model="form.password" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="space-y-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <Input id="password_confirmation" type="password" v-model="form.password_confirmation" />
                </div>

                <div class="space-y-2">
                    <Label for="role">Role</Label>
                    <select
                        id="role"
                        v-model="form.role"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="manager">Manager</option>
                        <option value="cashier">Cashier</option>
                    </select>
                    <InputError :message="form.errors.role" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="emit('update:open', false)">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ mode === 'create' ? 'Add Employee' : 'Save Changes' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>