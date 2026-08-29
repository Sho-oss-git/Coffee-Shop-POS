<script setup lang="ts">
import { Sun, Moon } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

function toggle() {
  updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
  <button
    type="button"
    @click="toggle"
    class="flex h-9 w-9 items-center justify-center rounded-full border border-[#E5E1DD] bg-white text-[#211812] shadow-sm transition-colors hover:bg-[#F7F3EE] dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
    aria-label="Toggle theme"
  >
    <Sun v-if="appearance === 'dark'" class="h-4 w-4" />
    <Moon v-else class="h-4 w-4" />
  </button>
</template>