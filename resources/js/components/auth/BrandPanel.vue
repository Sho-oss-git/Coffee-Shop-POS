<script setup lang="ts">
withDefaults(defineProps<{
  heroImage?: string;
}>(), {
  // Replace with your own asset, e.g. import coffeeHero from '@/assets/images/coffee-hero.jpg'
  heroImage: '/images/auth/coffee-hero.jpg',
});
</script>

<template>
  <div
    class="relative hidden md:flex md:w-[45%] flex-col justify-between overflow-hidden p-10 text-white"
    :style="{
      backgroundImage: `linear-gradient(180deg, rgba(31,57,48,0.92) 0%, rgba(31,57,48,0.55) 45%, rgba(31,57,48,0.95) 100%), url(${heroImage})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
    }"
  >
    <!-- Logo + Brand -->
    <div class="flex flex-col items-start gap-3">
      <div class="flex h-14 w-14 items-center justify-center rounded-full border border-white/30">
        <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 8h13a3 3 0 0 1 0 6h-1" />
          <path d="M3 8v7a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3V8" />
          <path d="M7 2c0 1-1.5 1.5-1.5 3S7 6.5 7 6.5" />
          <path d="M11 2c0 1-1.5 1.5-1.5 3S11 6.5 11 6.5" />
        </svg>
      </div>
      <div>
        <h1 class="text-4xl font-bold leading-none tracking-tight">JC66</h1>
        <p class="mt-1 text-sm font-medium tracking-[0.3em] text-white/80">COFFEE SHOP</p>
      </div>
    </div>

    <!-- Tagline -->
    <div class="mt-auto max-w-sm">
      <div class="mb-4 h-px w-12 bg-white/40" />
      <h2 class="font-serif text-3xl font-semibold leading-tight">
        Brewing Quality,<br />Serving Happiness.
      </h2>
      <p class="mt-4 text-sm leading-relaxed text-white/80">
        JC66 Coffee Shop Management System helps us serve you better. Manage sales,
        inventory, and reports with ease.
      </p>
    </div>

    <!-- Badge -->
    <div class="mt-8 inline-flex w-fit items-center gap-2 rounded-full bg-black/30 px-4 py-2 text-xs font-medium backdrop-blur-sm">
      <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M3 8h13a3 3 0 0 1 0 6h-1" />
        <path d="M3 8v7a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3V8" />
      </svg>
      Proudly serving since 2024.
    </div>
  </div>
</template>