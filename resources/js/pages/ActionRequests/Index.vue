    <script setup lang="ts">
    import AppLayout from '@/layouts/AppLayout.vue';
    import { type BreadcrumbItem } from '@/types';
    import { Head, useForm } from '@inertiajs/vue3';

    interface ActionRequest {
        id: number;
        type: string;
        target_type: string | null;
        target_id: number | null;
        target_name: string | null;
        reason: string;
        status: 'pending' | 'approved' | 'rejected';
        review_note: string | null;
        created_at: string;
        reviewed_at: string | null;
        requester: { name: string };
        reviewer: { name: string } | null;
    }

    const props = defineProps<{
        requests: ActionRequest[];
        canCreate: boolean;
        canReview: boolean;
    }>();

    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Action Requests', href: route('action-requests') }];
    const requestTypes = [
        { value: 'inventory_adjustment', label: 'Inventory adjustment' },
        { value: 'price_change', label: 'Price change' },
        { value: 'product_deletion', label: 'Product deletion' },
        { value: 'ingredient_deletion', label: 'Ingredient deletion' },
        { value: 'transaction_correction', label: 'Transaction correction' },
        { value: 'other', label: 'Other' },
    ];
    const reviewForms = new Map<number, ReturnType<typeof useForm>>();

    function reviewForm(id: number) {
        if (!reviewForms.has(id)) reviewForms.set(id, useForm({ status: 'approved', review_note: '' }));
        return reviewForms.get(id)!;
    }

    function reviewRequest(request: ActionRequest) {
        reviewForm(request.id).patch(route('action-requests.review', request.id), { preserveScroll: true });
    }

    function typeLabel(type: string) {
        return requestTypes.find((item) => item.value === type)?.label ?? type;
    }
    </script>

    <template>
        <Head title="Action Requests" />
        <AppLayout :breadcrumbs="breadcrumbs">
            <div class="flex min-w-0 flex-col gap-6 p-3 sm:p-4">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">Action Requests</h1>
                    <p class="text-sm text-foreground/60">Request Admin approval for sensitive operational changes.</p>
                </div>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="p-3 text-foreground/70">Request</th>
                                <th class="p-3 text-foreground/70">Submitted by</th>
                                <th class="p-3 text-foreground/70">Reason</th>
                                <th class="p-3 text-foreground/70">Status</th>
                                <th v-if="canReview" class="p-3 text-foreground/70">Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="request in props.requests" :key="request.id" class="border-b align-top last:border-0">
                                <td class="p-3"><div class="font-medium text-foreground">{{ typeLabel(request.type) }}</div><div v-if="request.target_name" class="text-xs text-foreground/60">{{ request.target_name }}</div><div class="text-xs text-foreground/60">{{ request.created_at }}</div></td>
                                <td class="p-3 text-foreground">{{ request.requester.name }}</td>
                                <td class="max-w-sm whitespace-pre-wrap p-3">{{ request.reason }}<div v-if="request.review_note" class="mt-2 text-xs text-foreground/60">Admin note: {{ request.review_note }}</div></td>
                                <td class="p-3"><span class="rounded-full bg-muted px-2 py-1 text-xs capitalize text-foreground">{{ request.status }}</span></td>
                                <td v-if="canReview" class="p-3">
                                    <div v-if="request.status === 'pending'" class="flex min-w-44 flex-col gap-2">
                                        <select v-model="reviewForm(request.id).status" class="h-9 rounded-md border bg-background px-2 text-xs"><option value="approved">Approve</option><option value="rejected">Reject</option></select>
                                        <input v-model="reviewForm(request.id).review_note" class="h-9 rounded-md border bg-background px-2 text-xs" placeholder="Optional note" />
                                        <button type="button" :disabled="reviewForm(request.id).processing" class="rounded-md bg-primary px-3 py-1.5 text-xs text-primary-foreground" @click="reviewRequest(request)">Submit review</button>
                                    </div>
                                    <span v-else class="text-xs text-foreground/60">Reviewed {{ request.reviewed_at }}</span>
                                </td>
                            </tr>
                            <tr v-if="props.requests.length === 0"><td :colspan="canReview ? 5 : 4" class="p-8 text-center text-foreground/60">No action requests yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    </template>
