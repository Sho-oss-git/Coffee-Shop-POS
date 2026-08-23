<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{ status: 'working' | 'break' | 'off_duty' }>();

const isOnBreak = computed(() => props.status === 'break');

function toggleBreak() {
    const nextStatus = isOnBreak.value ? 'working' : 'break';
    router.patch('/clock-status', { status: nextStatus }, { preserveScroll: true });
}
</script>

<template>
    <Button
        type="button"
        size="sm"
        :variant="isOnBreak ? 'default' : 'outline'"
        @click="toggleBreak"
    >
        {{ isOnBreak ? 'End Break' : 'Take Break' }}
    </Button>
</template>