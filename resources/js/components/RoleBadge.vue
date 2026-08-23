<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ role: 'admin' | 'manager' | 'cashier' }>();

const variant = computed(() => ({
    admin: 'default',
    manager: 'secondary',
    cashier: 'outline',
}[props.role] as 'default' | 'secondary' | 'outline'));

const label = computed(() => props.role.charAt(0).toUpperCase() + props.role.slice(1));
</script>

<template>
    <span
        class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold"
        :class="{
            'border-transparent bg-primary text-primary-foreground': variant === 'default',
            'border-transparent bg-secondary text-secondary-foreground': variant === 'secondary',
            'border-input bg-background': variant === 'outline',
        }"
    >
        {{ label }}
    </span>
</template>