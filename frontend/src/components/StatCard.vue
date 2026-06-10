<script setup lang="ts">
defineProps<{
  label: string
  value: string
  unit?: string
}>()
</script>

<template>
  <div
    data-testid="stat-card"
    class="glass flex flex-col rounded-2xl border border-hairline bg-glass px-4 py-3"
  >
    <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
      {{ label }}
    </span>
    <span class="font-serif text-3xl italic text-text">{{ value }}</span>
    <span v-if="unit" class="text-xs text-text-3">{{ unit }}</span>
  </div>
</template>
