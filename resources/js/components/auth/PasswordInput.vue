<script setup lang="ts">
import { ref } from 'vue';
import { Lock, Eye, EyeOff } from 'lucide-vue-next';
import IconInput from './IconInput.vue';

defineProps<{
  id: string;
  modelValue: string;
  placeholder?: string;
  autocomplete?: string;
  tabindex?: number | string;
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();

const visible = ref(false);
</script>

<template>
  <IconInput
    :id="id"
    :type="visible ? 'text' : 'password'"
    :model-value="modelValue"
    :placeholder="placeholder"
    :autocomplete="autocomplete"
    :tabindex="tabindex"
    @update:modelValue="$emit('update:modelValue', $event)"
  >
    <template #icon>
      <Lock class="h-4 w-4" />
    </template>
    <template #trailing>
      <button
        type="button"
        tabindex="-1"
        class="text-[#6B4532]/60 hover:text-[#1F3930] dark:text-white/40 dark:hover:text-white"
        @click="visible = !visible"
        :aria-label="visible ? 'Hide password' : 'Show password'"
      >
        <EyeOff v-if="visible" class="h-4 w-4" />
        <Eye v-else class="h-4 w-4" />
      </button>
    </template>
  </IconInput>
</template>