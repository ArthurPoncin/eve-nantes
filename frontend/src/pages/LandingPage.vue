<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import HeroBackdrop from '@/components/HeroBackdrop.vue'
import WeatherWidget from '@/components/WeatherWidget.vue'
import VenueList from '@/components/VenueList.vue'
import { fetchVenues } from '@/api/venues'
import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'
import type { Venue } from '@/types/venue'

const moods = [
  {
    id: 'festif',
    label: 'Festif',
    dot: 'bg-mood-festif',
    active: 'border-pink text-pink glow-pink',
  },
  {
    id: 'chill',
    label: 'Chill',
    dot: 'bg-mood-chill',
    active: 'border-cyan text-cyan glow-cyan',
  },
  {
    id: 'decouverte',
    label: 'Découverte',
    dot: 'bg-mood-decouverte',
    active: 'border-violet-bright text-violet-bright glow-violet',
  },
  {
    id: 'afterwork',
    label: 'Afterwork',
    dot: 'bg-mood-afterwork',
    active: 'border-gold text-gold glow-gold',
  },
] as const

const venues = ref<Venue[]>([])
const activeMood = ref<string | null>(null)
const query = ref('')
const isLoading = ref(true)
const hasError = ref(false)

const filteredVenues = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return venues.value
  return venues.value.filter(
    (v) => v.name.toLowerCase().includes(q) || v.city.toLowerCase().includes(q),
  )
})

// On présente une sélection (comme la maquette), pas l'annuaire complet :
// la recherche et l'ambiance affinent la liste pour atteindre le reste.
const VENUE_LIMIT = 18
const displayedVenues = computed(() => filteredVenues.value.slice(0, VENUE_LIMIT))
const hiddenCount = computed(() => Math.max(0, filteredVenues.value.length - VENUE_LIMIT))

// Comptes par ambiance, figés depuis le chargement non filtré (pastilles).
const moodCounts = ref<Record<string, number>>({})

function moodLabelFor(id: string | null): string {
  return moods.find((m) => m.id === id)?.label ?? 'Spots'
}

async function loadVenues(mood?: string) {
  isLoading.value = true
  hasError.value = false
  try {
    const result = await fetchVenues(mood)
    venues.value = Array.isArray(result) ? result : []
    if (mood === undefined) {
      const counts: Record<string, number> = {}
      for (const item of venues.value) {
        if (item.mood) counts[item.mood] = (counts[item.mood] ?? 0) + 1
      }
      moodCounts.value = counts
    }
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
  // L'utilisateur connecté arrive ici après le login : on charge ses favoris
  // pour que les cœurs reflètent l'état serveur. Fire-and-forget.
  const auth = useAuthStore()
  if (auth.isAuthenticated) {
    useFavoritesStore()
      .load()
      .catch(() => {})
  }
})
</script>

<template>
  <main class="w-full pb-24">
    <!-- HERO plein cadre : décor (astre couchant + skyline) en fond, contenu centré -->
    <section class="relative isolate overflow-hidden">
      <HeroBackdrop class="absolute inset-0 z-0" />
      <div
        class="relative z-10 mx-auto flex w-full max-w-5xl flex-col items-center gap-9 px-6 pb-20 pt-16 text-center"
      >
        <p class="font-mono text-[11px] uppercase tracking-[0.3em] text-text-3">
          Nantes · Nightlife
        </p>
        <h1
          class="max-w-3xl font-serif text-5xl italic leading-[1.05] text-text md:text-7xl"
        >
          La nuit <span class="text-pink">est à toi</span>, ce soir.
        </h1>
        <p class="max-w-md text-base text-text-2">
          Bars, clubs et événements nantais, recommandés selon ton humeur.
        </p>

        <RouterLink
          to="/soiree"
          class="glow-pink rounded-full bg-pink px-7 py-3.5 font-mono text-[11px] uppercase tracking-[0.2em] text-white transition hover:bg-pink-bright"
        >
          ✨ Compose ma soirée
        </RouterLink>

        <form
          class="glass flex w-full max-w-md items-center gap-2 rounded-full border border-hairline bg-glass py-1.5 pl-5 pr-1.5"
          @submit.prevent
        >
          <input
            v-model="query"
            type="search"
            placeholder="Bar, club, quartier…"
            aria-label="Rechercher un lieu"
            class="min-w-0 flex-1 bg-transparent text-sm text-text placeholder:text-text-3 focus:outline-none"
          />
          <span
            class="glow-pink rounded-full bg-pink px-4 py-2 font-mono text-[10px] uppercase tracking-[0.18em] text-white"
          >
            Filtrer
          </span>
        </form>

        <ul class="flex flex-wrap items-center justify-center gap-3">
          <li v-for="mood in moods" :key="mood.id">
            <button
              type="button"
              data-testid="mood-filter"
              :data-mood="mood.id"
              :aria-pressed="activeMood === mood.id"
              class="glass flex items-center gap-2 rounded-full border px-4 py-2 font-mono text-[11px] uppercase tracking-[0.16em] transition"
              :class="
                activeMood === mood.id
                  ? mood.active
                  : 'border-hairline bg-glass text-text-2 hover:border-hairline-bright hover:text-text'
              "
              @click="selectMood(mood.id)"
            >
              <span class="h-2 w-2 rounded-full" :class="mood.dot" />
              {{ mood.label }}
              <span v-if="moodCounts[mood.id]" class="ml-0.5 opacity-60">{{
                moodCounts[mood.id]
              }}</span>
            </button>
          </li>
        </ul>

        <WeatherWidget class="w-full max-w-xs" />
      </div>
    </section>

    <!-- LISTE des lieux -->
    <div class="mx-auto w-full max-w-5xl px-6 pt-8">
      <section data-testid="venue-list" class="w-full text-left">
        <div
          v-if="!isLoading && !hasError && filteredVenues.length > 0"
          class="mb-4 flex items-baseline justify-between"
        >
          <h2 class="font-serif text-2xl italic text-text">
            {{ activeMood ? moodLabelFor(activeMood) : 'Tous les spots' }}
          </h2>
          <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
            {{ filteredVenues.length }} {{ filteredVenues.length > 1 ? 'lieux' : 'lieu' }}
          </span>
        </div>

        <p
          v-if="isLoading"
          data-testid="venue-loading"
          class="text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
        >
          Chargement des lieux…
        </p>
        <p
          v-else-if="hasError"
          data-testid="venue-error"
          class="text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
        >
          Impossible de charger les lieux.
        </p>
        <p
          v-else-if="filteredVenues.length === 0"
          data-testid="venue-empty"
          class="text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
        >
          Aucun lieu pour cette ambiance.
        </p>
        <template v-else>
          <VenueList :venues="displayedVenues" />
          <p
            v-if="hiddenCount > 0"
            class="mt-6 text-center font-mono text-[10px] uppercase tracking-[0.18em] text-text-3"
          >
            + {{ hiddenCount }} autres lieux · affine avec la recherche ou l'ambiance
          </p>
        </template>
      </section>
    </div>
  </main>
</template>
