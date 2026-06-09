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
  <main class="mx-auto flex min-h-[80vh] w-full max-w-xl flex-col gap-6 px-6 py-12">
    <header class="flex flex-col gap-1">
      <p class="font-mono text-xs uppercase tracking-[0.3em] text-ink-muted">
        Tes lieux
      </p>
      <h1 class="font-serif text-4xl italic text-ink-primary">Favoris</h1>
    </header>

    <p
      v-if="isLoading"
      data-testid="favorites-loading"
      class="font-mono text-xs uppercase tracking-widest text-ink-muted"
    >
      Chargement des favoris…
    </p>
    <p
      v-else-if="hasError"
      data-testid="favorites-error"
      class="font-mono text-xs uppercase tracking-widest text-ink-muted"
    >
      Impossible de charger les favoris.
    </p>
    <p
      v-else-if="favorites.venues.length === 0"
      data-testid="favorites-empty"
      class="font-mono text-xs uppercase tracking-widest text-ink-muted"
    >
      Aucun favori pour l'instant.
    </p>
    <VenueList v-else :venues="favorites.venues" />
  </main>
</template>
