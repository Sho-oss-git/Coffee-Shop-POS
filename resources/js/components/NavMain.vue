<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

type NavItemWithBadge = NavItem & { badge?: number };

export type NavGroup = {
    label: string;
    items: NavItemWithBadge[];
};

defineProps<{
    groups: NavGroup[];
}>();

const page = usePage();
</script>

<template>
    <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-0">
        <SidebarGroupLabel class="text-[11px] font-semibold tracking-wide uppercase opacity-60">
            {{ group.label }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton as-child :is-active="item.href === page.url" :tooltip="item.title" class="rounded-lg">
                    <Link :href="item.href!">
                        <component :is="item.icon" v-if="item.icon" />
                        <span>{{ item.title }}</span>
                        <span v-if="item.badge" class="ml-auto rounded-full bg-destructive px-1.5 text-[10px] font-semibold text-destructive-foreground">
                            {{ item.badge > 99 ? '99+' : item.badge }}
                        </span>
                        <ChevronRight v-else-if="item.href === page.url" class="ml-auto size-4 opacity-70" />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>