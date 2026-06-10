<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchPilier } from '@/api/stats'
import type { Pilier } from '@/types/stats'

const props = defineProps<{ slug: string }>()

const pilier = ref<Pilier | null>(null)
const isLoading = ref(true)
const hasError = ref(false)

onMounted(async () => {
  try {
    pilier.value = await fetchPilier(props.slug)
  } catch {
    // Classement indisponible : on masque la carte.
    hasError.value = true
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div
    v-if="isLoading"
    data-testid="pilier-skeleton"
    class="h-16 animate-pulse rounded-2xl border border-hairline bg-glass"
    aria-hidden="true"
  />

  <section
    v-else-if="!hasError"
    data-testid="pilier-card"
    class="glass flex items-center gap-4 rounded-2xl border bg-glass px-5 py-4"
    :class="pilier ? 'border-gold/40' : 'border-hairline'"
  >
    <span class="text-2xl" aria-hidden="true">👑</span>
    <div v-if="pilier" class="flex flex-col">
      <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-gold">
        Pilier de bar
      </span>
      <span data-testid="pilier-username" class="font-serif text-lg italic text-text">
        {{ pilier.username }}
      </span>
      <span class="text-xs text-text-3">
        {{ pilier.checkins_count }} passages ces 90 derniers jours
      </span>
    </div>
    <div v-else class="flex flex-col">
      <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
        Pilier de bar
      </span>
      <span data-testid="pilier-empty" class="text-sm text-text-2">
        Le trône est libre — deviens le Pilier de ce bar.
      </span>
    </div>
  </section>
</template>
