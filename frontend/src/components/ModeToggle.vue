<script setup lang="ts">
import { computed } from 'vue'
import { useTheme } from '@/composables/useTheme'

const { theme, toggle } = useTheme()

const label = computed(() => (theme.value === 'night' ? 'Sunset' : 'Night'))
const ariaLabel = computed(
  () => `Basculer en mode ${theme.value === 'night' ? 'sunset' : 'night'}`,
)
</script>

<template>
  <button
    type="button"
    :aria-label="ariaLabel"
    class="rounded-full border border-white/15 bg-white/5 px-4 py-1.5 font-mono text-xs uppercase tracking-[0.25em] text-ink-muted transition-colors hover:border-white/30 hover:text-ink-primary"
    @click="toggle"
  >
    {{ label }}
  </button>
</template>
