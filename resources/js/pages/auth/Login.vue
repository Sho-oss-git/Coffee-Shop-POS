<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail, Coffee, User } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import BrandPanel from '@/components/auth/BrandPanel.vue';
import IconInput from '@/components/auth/IconInput.vue';
import PasswordInput from '@/components/auth/PasswordInput.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';

defineProps<{
  status?: string;
  canResetPassword: boolean;
}>();

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

const submit = () => {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <Head title="Log in" />

  <div class="flex min-h-screen items-center justify-center bg-[#F7F3EE] p-4 dark:bg-[#101A16]">
    <div class="flex w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-[#16221D]">
      <BrandPanel />

      <!-- Right side -->
      <div class="relative flex w-full flex-col justify-center px-6 py-10 sm:px-10 md:w-[55%] md:px-14">
        <div class="absolute right-6 top-6">
          <ThemeToggle />
        </div>

        <!-- Mobile brand -->
        <div class="mb-8 flex flex-col items-center gap-1 md:hidden">
          <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#1F3930]/10 text-[#1F3930] dark:bg-white/10 dark:text-white">
            <Coffee class="h-6 w-6" />
          </div>
          <h1 class="text-2xl font-bold text-[#211812] dark:text-white">JC66</h1>
          <p class="text-xs tracking-[0.3em] text-[#6B4532] dark:text-white/60">COFFEE SHOP</p>
        </div>

        <div class="mx-auto w-full max-w-sm">
          <div class="mb-8 hidden flex-col items-start md:flex">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-[#1F3930]/10 dark:bg-white/10">
              <Coffee class="h-6 w-6 text-[#1F3930] dark:text-white" />
            </div>
            <h2 class="text-3xl font-bold text-[#211812] dark:text-white">Welcome Back!</h2>
            <p class="mt-1 text-sm text-[#211812]/60 dark:text-white/50">Sign in to continue to your account</p>
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
                required
                autofocus
                tabindex="1"
                autocomplete="email"
                v-model="form.email"
                placeholder="Enter your email address"
              >
                <template #icon><Mail class="h-4 w-4" /></template>
              </IconInput>
              <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
              <Label for="password" class="text-sm font-semibold text-[#211812] dark:text-white">Password</Label>
              <PasswordInput
                id="password"
                tabindex="2"
                autocomplete="current-password"
                v-model="form.password"
                placeholder="Enter your password"
              />
              <InputError :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between text-sm">
              <Label for="remember" class="flex cursor-pointer items-center gap-2 text-[#211812]/80 dark:text-white/70">
                <Checkbox id="remember" v-model:checked="form.remember" tabindex="3" />
                Remember me
              </Label>
              <TextLink
                v-if="canResetPassword"
                :href="route('password.request')"
                tabindex="4"
                class="font-medium text-[#1F3930] hover:underline dark:text-[#8FBFA8]"
              >
                Forgot password?
              </TextLink>
            </div>

            <button
              type="submit"
              tabindex="5"
              :disabled="form.processing"
              class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#1F3930] text-sm font-semibold text-white shadow-md transition-all hover:bg-[#284A3D] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-70"
            >
              <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
              <Coffee v-else class="h-4 w-4" />
              Sign In
            </button>

            <div class="flex items-center gap-3 text-xs text-[#211812]/40 dark:text-white/30">
              <span class="h-px flex-1 bg-[#E5E1DD] dark:bg-white/10" />
              or continue with
              <span class="h-px flex-1 bg-[#E5E1DD] dark:bg-white/10" />
            </div>

            <TextLink
              :href="route('login', { as: 'cashier' })"
              tabindex="6"
              class="flex h-12 w-full items-center justify-center gap-2 rounded-xl border border-[#E5E1DD] text-sm font-semibold text-[#211812] transition-colors hover:bg-[#F7F3EE] dark:border-white/10 dark:text-white dark:hover:bg-white/5"
            >
              <User class="h-4 w-4" />
              Sign in as Cashier
            </TextLink>
          </form>

          <p class="mt-10 text-center text-xs text-[#211812]/40 dark:text-white/30">
            © 2024 JC66 Coffee Shop. All rights reserved.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>