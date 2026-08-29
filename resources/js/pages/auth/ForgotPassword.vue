<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import BrandPanel from '@/components/auth/BrandPanel.vue';
import IconInput from '@/components/auth/IconInput.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';

defineProps<{
  status?: string;
}>();

const form = useForm({
  email: '',
});

const submit = () => {
  form.post(route('password.email'));
};
</script>

<template>
  <Head title="Forgot password" />

  <div class="flex min-h-screen items-center justify-center bg-[#F7F3EE] p-4 dark:bg-[#101A16]">
    <div class="flex w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-[#16221D]">
      <BrandPanel />

      <!-- Right side -->
      <div class="relative flex w-full flex-col justify-center px-6 py-10 sm:px-10 md:w-[55%] md:px-14">
        <div class="absolute right-6 top-6">
          <ThemeToggle />
        </div>

        <div class="mx-auto w-full max-w-sm">
          <div class="mb-8 flex flex-col items-start">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-[#1F3930]/10 dark:bg-white/10">
              <Mail class="h-6 w-6 text-[#1F3930] dark:text-white" />
            </div>
            <h2 class="text-3xl font-bold text-[#211812] dark:text-white">Forgot Password?</h2>
            <p class="mt-1 text-sm text-[#211812]/60 dark:text-white/50">
              Enter your email to receive a password reset link
            </p>
          </div>

          <div v-if="status" class="mb-4 rounded-lg bg-green-50 px-3 py-2 text-center text-sm font-medium text-green-700">
            {{ status }}
          </div>

          <form @submit.prevent="submit" class="flex flex-col gap-5">
            <div class="grid gap-2">
              <Label for="email" class="text-sm font-semibold text-[#211812] dark:text-white">Email Address</Label>
              <IconInput
                id="email"
                type="email"
                name="email"
                autocomplete="off"
                autofocus
                v-model="form.email"
                placeholder="Enter your email address"
              >
                <template #icon><Mail class="h-4 w-4" /></template>
              </IconInput>
              <InputError :message="form.errors.email" />
            </div>

            <button
              type="submit"
              :disabled="form.processing"
              class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#1F3930] text-sm font-semibold text-white shadow-md transition-all hover:bg-[#284A3D] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-70"
            >
              <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
              Email password reset link
            </button>
          </form>

          <div class="mt-6 space-x-1 text-center text-sm text-[#211812]/50 dark:text-white/40">
            <span>Or, return to</span>
            <TextLink :href="route('login')" class="font-medium text-[#1F3930] hover:underline dark:text-[#8FBFA8]">
              log in
            </TextLink>
          </div>

          <p class="mt-10 text-center text-xs text-[#211812]/40 dark:text-white/30">
            © 2024 JC66 Coffee Shop. All rights reserved.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>