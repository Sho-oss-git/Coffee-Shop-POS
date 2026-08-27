<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

// Components
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const { isAdmin } = usePermissions();
const page = usePage<{ auth: { user: { id: number } } }>();
const currentUserId = computed(() => page.props.auth.user.id);

const passwordInput = ref<HTMLInputElement | null>(null);

// Admin deletes their own account directly; everyone else must request
// Admin approval via an Action Request (see ActionRequestController).
const form = useForm({ password: '' });

const requestForm = useForm({
    type: 'user_deletion',
    reason: '',
    target_type: 'user',
    target_id: 0,
});

const closeModal = () => {
    form.clearErrors();
    form.reset();
    requestForm.clearErrors();
    requestForm.reset();
};

const deleteUser = (e: Event) => {
    e.preventDefault();

    if (isAdmin.value) {
        form.delete(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.value?.focus(),
            onFinish: () => form.reset(),
        });
        return;
    }

    requestForm.target_id = currentUserId.value;
    if (!requestForm.reason) {
        requestForm.reason = 'Request to delete my own account.';
    }
    requestForm.post(route('action-requests.store'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish: () => requestForm.reset(),
    });
};
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Delete account" description="Delete your account and all of its resources" />
        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">Warning</p>
                <p class="text-sm">Please proceed with caution, this cannot be undone.</p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive">Delete account</Button>
                </DialogTrigger>
                <DialogContent>
                    <form class="space-y-6" @submit="deleteUser">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Are you sure you want to delete your account?</DialogTitle>
                            <DialogDescription v-if="isAdmin">
                                Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your
                                password to confirm you would like to permanently delete your account.
                            </DialogDescription>
                            <DialogDescription v-else>
                                Your request will be sent to an Admin for approval. Please add an optional reason below.
                            </DialogDescription>
                        </DialogHeader>

                        <!-- Admin: password confirmation for immediate deletion -->
                        <div v-if="isAdmin" class="grid gap-2">
                            <Label for="password" class="sr-only">Password</Label>
                            <Input id="password" type="password" name="password" ref="passwordInput" v-model="form.password" placeholder="Password" />
                            <InputError :message="form.errors.password" />
                        </div>

                        <!-- Non-admin: reason for the deletion request -->
                        <div v-else class="grid gap-2">
                            <Label for="delete-reason">Reason (optional)</Label>
                            <textarea
                                id="delete-reason"
                                v-model="requestForm.reason"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Why do you want your account deleted?"
                            />
                            <InputError :message="requestForm.errors.reason" />
                        </div>

                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="secondary" @click="closeModal"> Cancel </Button>
                            </DialogClose>

                            <Button v-if="isAdmin" variant="destructive" :disabled="form.processing">
                                <button type="submit">Delete account</button>
                            </Button>
                            <Button v-else variant="destructive" :disabled="requestForm.processing">
                                <button type="submit">Send Request</button>
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
