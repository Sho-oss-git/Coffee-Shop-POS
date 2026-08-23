<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { BookOpen, ClipboardList, Folder, LayoutGrid, Package, PieChart, Receipt, Settings, ShoppingCart, Users, Warehouse } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { usePermissions } from '@/composables/usePermissions';

const { role } = usePermissions();

type RoleNavItem = NavItem & { roles?: ('admin' | 'manager' | 'cashier')[] };
const page = usePage<{ pending_action_requests?: number }>();

const allNavItems: RoleNavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
        roles: ['admin', 'manager', 'cashier'],
    },
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
        title: 'Sales',
        icon: ShoppingCart,
        roles: ['admin', 'manager'],
        items: [
            {
                title: 'Sales Transaction',
                href: '/sale-transaction',
            },
            {
                title: 'Sales History',
                href: '/sales-history',
            },
        ],
    },
    {
        // Cashier gets its own direct link — the admin/manager "Sales"
        // group above points at /sales-history, which a cashier can't
        // access (role:admin,manager middleware). This links straight to
        // the cashier-scoped history of their own transactions instead.
        title: 'Transaction History',
        href: route('cashier.transactions.history'),
        icon: Receipt,
        roles: ['cashier'],
    },
    {
        title: 'Inventory Monitoring',
        icon: Warehouse,
        roles: ['admin', 'manager'],
        items: [
            {
                title: 'Ingredients',
                href: '/inventory/ingredients',
            },
            {
                title: 'Cookies',
                href: '/inventory/cookies',
            },
        ],
    },
    {
        title: 'Action Request',
        href: '/action-requests',
        icon: ClipboardList,
        roles: ['admin', 'manager', ],
    },
    {
        title: 'Report Analytics',
        icon: PieChart,
        roles: ['admin', 'manager'],
        items: [
            {
                title: 'Sales Report',
                href: '/reports/sales',
            },
            {
                title: 'Inventory Report',
                href: '/reports/inventory',
            },
            {
                title: 'Attendance Report',
                href: '/reports/attendance',
            },
        ],
    },
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
];

const mainNavItems = computed(() =>
    allNavItems
        .filter((item) => !item.roles || item.roles.includes(role.value))
        .map((item) => (item.title === 'Action Request' && (page.props.pending_action_requests ?? 0) > 0
            ? { ...item, badge: page.props.pending_action_requests }
            : item)),
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>