<script setup lang="ts">
import ModeToggle from './ModeToggle.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <header class="flex items-center justify-between border-b border-white/5 px-6 py-4">
      <span class="font-serif text-2xl italic text-ink-primary">NOCTAMBULE</span>
      <ModeToggle />
    </header>
    <slot />
  </div>
</template>
