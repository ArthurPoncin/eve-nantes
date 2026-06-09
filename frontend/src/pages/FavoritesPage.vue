<script setup lang="ts">
import { onMounted, ref } from 'vue'
import VenueList from '@/components/VenueList.vue'
import { useFavoritesStore } from '@/stores/favorites'

const favorites = useFavoritesStore()
const isLoading = ref(true)
const hasError = ref(false)

onMounted(async () => {
  try {
    await favorites.load()
  } catch {
    hasError.value = true
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <main class="mx-auto flex min-h-[80vh] w-full max-w-xl flex-col gap-6 px-6 pb-24 pt-12">
    <header class="flex flex-col gap-1">
      <p class="font-mono text-[11px] uppercase tracking-[0.3em] text-text-3">Tes lieux</p>
      <h1 class="font-serif text-4xl italic text-text">
        Favoris<span class="text-pink">.</span>
      </h1>
    </header>

    <p
      v-if="isLoading"
      data-testid="favorites-loading"
      class="rounded-2xl border border-hairline bg-glass px-5 py-8 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Chargement des favoris…
    </p>
    <p
      v-else-if="hasError"
      data-testid="favorites-error"
      class="rounded-2xl border border-hairline bg-glass px-5 py-8 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Impossible de charger les favoris.
    </p>
    <p
      v-else-if="favorites.venues.length === 0"
      data-testid="favorites-empty"
      class="rounded-2xl border border-hairline bg-glass px-5 py-8 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Aucun favori pour l'instant.
    </p>
    <VenueList v-else :venues="favorites.venues" />
  </main>
</template>
