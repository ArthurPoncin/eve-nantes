<script setup lang="ts">
import { onMounted, ref } from 'vue'
import WeatherWidget from '@/components/WeatherWidget.vue'
import VenueList from '@/components/VenueList.vue'
import { fetchVenues } from '@/api/venues'
import type { Venue } from '@/types/venue'

const moods = [
  { id: 'festif', label: 'Festif', dotClass: 'bg-mood-festif' },
  { id: 'chill', label: 'Chill', dotClass: 'bg-mood-chill' },
  { id: 'decouverte', label: 'Découverte', dotClass: 'bg-mood-decouverte' },
  { id: 'afterwork', label: 'Afterwork', dotClass: 'bg-mood-afterwork' },
] as const

const venues = ref<Venue[]>([])
const activeMood = ref<string | null>(null)
const isLoading = ref(true)
const hasError = ref(false)

async function loadVenues(mood?: string) {
  isLoading.value = true
  hasError.value = false
  try {
    venues.value = await fetchVenues(mood)
  } catch {
    hasError.value = true
    venues.value = []
  } finally {
    isLoading.value = false
  }
}

function selectMood(moodId: string) {
  if (activeMood.value === moodId) {
    activeMood.value = null
    loadVenues()
    return
  }
  activeMood.value = moodId
  loadVenues(moodId)
}

onMounted(() => {
  loadVenues()
})
</script>

<template>
  <main
    class="flex min-h-[80vh] flex-col items-center justify-center gap-8 px-6 text-center"
  >
    <p class="font-mono text-xs uppercase tracking-[0.3em] text-ink-muted">
      La nuit nantaise
    </p>
    <h1 class="font-serif text-5xl italic leading-tight text-ink-primary md:text-7xl">
      Recommandée pour toi
    </h1>
    <p class="max-w-md text-base text-ink-muted">
      Choisis ton humeur, on s'occupe du reste.
    </p>
    <ul class="flex flex-wrap items-center justify-center gap-3">
      <li v-for="mood in moods" :key="mood.id">
        <button
          type="button"
          data-testid="mood-filter"
          :data-mood="mood.id"
          :aria-pressed="activeMood === mood.id"
          class="flex items-center gap-2 rounded-full border px-3 py-1 font-mono text-[10px] uppercase tracking-widest transition-colors"
          :class="
            activeMood === mood.id
              ? 'border-white/40 bg-white/15 text-ink-primary'
              : 'border-white/10 bg-white/5 text-ink-muted hover:border-white/20'
          "
          @click="selectMood(mood.id)"
        >
          <span class="h-2 w-2 rounded-full" :class="mood.dotClass" />
          {{ mood.label }}
        </button>
      </li>
    </ul>
    <WeatherWidget class="w-full max-w-xs" />

    <section data-testid="venue-list" class="w-full max-w-xl">
      <p
        v-if="isLoading"
        data-testid="venue-loading"
        class="font-mono text-xs uppercase tracking-widest text-ink-muted"
      >
        Chargement des lieux…
      </p>
      <p
        v-else-if="hasError"
        data-testid="venue-error"
        class="font-mono text-xs uppercase tracking-widest text-ink-muted"
      >
        Impossible de charger les lieux.
      </p>
      <p
        v-else-if="venues.length === 0"
        data-testid="venue-empty"
        class="font-mono text-xs uppercase tracking-widest text-ink-muted"
      >
        Aucun lieu pour cette ambiance.
      </p>
      <VenueList v-else :venues="venues" />
    </section>
  </main>
</template>
