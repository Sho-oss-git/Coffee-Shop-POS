<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain, { type NavGroup } from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    BookOpen,
    ClipboardList,
    Folder,
    LayoutGrid,
    Package,
    PieChart,
    Receipt,
    Settings,
    ShoppingCart,
    Users,
    Warehouse,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { usePermissions } from '@/composables/usePermissions';

const { role } = usePermissions();

type Role = 'admin' | 'manager' | 'cashier';
type RoleNavItem = NavItem & { roles?: Role[] };
// label can be a plain string (same for everyone) or a per-role map,
// e.g. { admin: 'Admin', manager: 'Account', cashier: 'Account' }.
type RoleNavGroup = { label: string | Partial<Record<Role, string>>; items: RoleNavItem[] };
const page = usePage<{ pending_action_requests?: number }>();

// Items are now organized as flat, labeled sections instead of
// title -> nested items[] dropdowns. Each item links directly;
// former sub-pages (e.g. "Sales Transaction") are now their own
// top-level entries inside a section, matching the flat pill-list
// sidebar design.
const allNavGroups: RoleNavGroup[] = [
    {
        label: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: '/dashboard',
                icon: LayoutGrid,
                roles: ['admin', 'manager', 'cashier'],
            },
        ],
    },
    {
        label: 'Sales',
        items: [
            {
                title: 'Products',
                href: route('products.index'),
                icon: Package,
                roles: ['admin', 'manager'],
            },
            {
                title: 'Products',
                href: route('cashier.products.index'),
                icon: Package,
                roles: ['cashier'],
            },
            {
                title: 'Sales Transaction',
                href: '/sale-transaction',
                icon: ShoppingCart,
                roles: ['admin', 'manager'],
            },
            {
                title: 'Sales History',
                href: '/sales-history',
                icon: Receipt,
                roles: ['admin', 'manager'],
            },
            {
                // Cashier gets its own direct link — the admin/manager "Sales History"
                // above points at /sales-history, which a cashier can't access
                // (role:admin,manager middleware). This links straight to the
                // cashier-scoped history of their own transactions instead.
                title: 'Transaction History',
                href: route('cashier.transactions.history'),
                icon: Receipt,
                roles: ['cashier'],
            },
        ],
    },
    {
        label: 'Inventory',
        items: [
            {
                title: 'Ingredients',
                href: '/inventory/ingredients',
                icon: Warehouse,
                roles: ['admin', 'manager'],
            },
            {
                title: 'Cookies',
                href: '/inventory/cookies',
                icon: Warehouse,
                roles: ['admin', 'manager'],
            },
        ],
    },
    {
        label: 'Reports',
        items: [
            {
                title: 'Action Request',
                href: '/action-requests',
                icon: ClipboardList,
                roles: ['admin', 'manager'],
            },
            {
                title: 'Sales Report',
                href: '/reports/sales',
                icon: PieChart,
                roles: ['admin', 'manager'],
            },
            {
                title: 'Inventory Report',
                href: '/reports/inventory',
                icon: PieChart,
                roles: ['admin', 'manager'],
            },
            {
                title: 'Attendance Report',
                href: '/reports/attendance',
                icon: PieChart,
                roles: ['admin', 'manager'],
            },
        ],
    },
    {
        label: { admin: 'Admin', manager: 'Account', cashier: 'Account' },
        items: [
            {
                title: 'User Management',
                href: '/user-management',
                icon: Users,
                roles: ['admin'],
            },
            {
                title: 'Settings',
                href: '/settings/profile',
                icon: Settings,
                roles: ['admin', 'manager', 'cashier'],
            },
        ],
    },
];

const mainNavGroups = computed<NavGroup[]>(() =>
    allNavGroups
        .map((group) => ({
            label: typeof group.label === 'string' ? group.label : (group.label[role.value] ?? Object.values(group.label)[0]),
            items: group.items
                .filter((item) => !item.roles || item.roles.includes(role.value))
                .map((item) =>
                    item.title === 'Action Request' && (page.props.pending_action_requests ?? 0) > 0
                        ? { ...item, badge: page.props.pending_action_requests }
                        : item,
                ),
        }))
        .filter((group) => group.items.length > 0),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="mainNavGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>