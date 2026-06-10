<script setup lang="ts">
defineProps<{
  count: number
  active: boolean
}>()
const emit = defineEmits<{ (e: 'toggle'): void }>()
</script>

<template>
  <button
    type="button"
    data-testid="kudos-button"
    :aria-pressed="active"
    class="flex items-center gap-2 rounded-full border px-4 py-1.5 font-mono text-[10px] uppercase tracking-[0.18em] transition"
    :class="
      active
        ? 'glow-gold border-gold/50 text-gold'
        : 'border-hairline text-text-2 hover:border-gold/40 hover:text-gold'
    "
    @click.stop="emit('toggle')"
  >
    <span aria-hidden="true">🥂</span>
    Santé !
    <span data-testid="kudos-count">{{ count }}</span>
  </button>
</template>
