<script setup lang="ts">
defineProps<{
  id: string;
  modelValue: string;
  type?: string;
  placeholder?: string;
  autocomplete?: string;
  autofocus?: boolean;
  tabindex?: number | string;
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
  <div class="relative">
    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[#6B4532]/60 dark:text-[#F7F3EE]/50">
      <slot name="icon" />
    </span>
    <input
      :id="id"
      :type="type ?? 'text'"
      :value="modelValue"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :autofocus="autofocus"
      :tabindex="tabindex"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      class="h-12 w-full rounded-xl border border-[#E5E1DD] bg-white pl-11 pr-4 text-sm text-[#211812] placeholder:text-[#211812]/40 outline-none transition-colors focus:border-[#1F3930] focus:ring-2 focus:ring-[#1F3930]/20 dark:border-white/10 dark:bg-white/5 dark:text-[#F7F3EE] dark:placeholder:text-white/30 dark:focus:border-[#3F6353]"
    />
    <span class="absolute inset-y-0 right-3 flex items-center">
      <slot name="trailing" />
    </span>
  </div>
</template>