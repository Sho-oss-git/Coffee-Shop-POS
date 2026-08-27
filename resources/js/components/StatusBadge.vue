<script setup lang="ts">
defineProps<{ status: 'working' | 'break' | 'off_duty' }>();

const labels: Record<string, string> = {
    working: 'Working',
    break: 'On Break',
    off_duty: 'Off Duty',
};

const dotColor: Record<string, string> = {
    working: 'bg-green-500',
    break: 'bg-amber-500',
    off_duty: 'bg-gray-400',
};
</script>

<template>
    <span class="inline-flex items-center gap-1.5 text-sm text-foreground">
        <span class="h-2 w-2 rounded-full" :class="dotColor[status]" />
        {{ labels[status] }}
    </span>
</template>