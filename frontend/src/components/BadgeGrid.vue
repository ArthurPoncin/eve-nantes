<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { fetchBadges } from '@/api/badges'
import type { Badge } from '@/types/badge'

const badges = ref<Badge[] | null>(null)
const isLoading = ref(true)

const unlockedCount = computed(
  () => badges.value?.filter((badge) => badge.unlocked).length ?? 0,
)

onMounted(async () => {
  try {
    badges.value = await fetchBadges()
  } catch {
    // Badges indisponibles (session expirée…) : on masque la grille.
    badges.value = null
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div
    v-if="isLoading"
    data-testid="badge-skeleton"
    class="h-24 animate-pulse rounded-2xl border border-hairline bg-glass"
    aria-hidden="true"
  />

  <section v-else-if="badges" data-testid="badge-grid" class="flex flex-col gap-3">
    <div class="flex items-baseline justify-between">
      <h2 class="font-mono text-[10px] uppercase tracking-[0.3em] text-text-3">Badges</h2>
      <span
        data-testid="badge-count"
        class="font-mono text-[10px] uppercase tracking-[0.18em] text-gold"
      >
        {{ unlockedCount }}/{{ badges.length }}
      </span>
    </div>

    <ul class="grid grid-cols-2 gap-3">
      <li
        v-for="badge in badges"
        :key="badge.id"
        data-testid="badge-item"
        class="flex flex-col gap-1 rounded-2xl border px-4 py-3"
        :class="
          badge.unlocked
            ? 'glow-gold border-gold/40 bg-glass'
            : 'border-hairline bg-glass opacity-50'
        "
      >
        <span
          class="text-2xl"
          :class="badge.unlocked ? 'text-gold' : 'text-text-3'"
          aria-hidden="true"
        >
          {{ badge.icon }}
        </span>
        <span
          :data-testid="badge.unlocked ? 'badge-unlocked' : 'badge-locked'"
          class="font-serif italic"
          :class="badge.unlocked ? 'text-text' : 'text-text-2'"
        >
          {{ badge.label }}
        </span>
        <span class="text-xs text-text-3">{{ badge.description }}</span>
        <span v-if="!badge.unlocked" class="sr-only">verrouillé</span>
      </li>
    </ul>
  </section>
</template>
