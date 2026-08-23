import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { User } from '@/types';

export function usePermissions() {
    const page = usePage<{ auth: { user: User } }>();
    const role = computed(() => page.props.auth.user.role);

    return {
        role,
        isAdmin: computed(() => role.value === 'admin'),
        isManager: computed(() => role.value === 'manager'),
        isCashier: computed(() => role.value === 'cashier'),
        canManageUsers: computed(() => role.value === 'admin'),
        canManageInventory: computed(() => role.value === 'admin' || role.value === 'manager'),
        canViewReports: computed(() => role.value === 'admin' || role.value === 'manager'),
    };
}