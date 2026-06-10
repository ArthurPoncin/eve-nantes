<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import VenuesMap from '@/components/VenuesMap.vue'
import { fetchVenues } from '@/api/venues'
import type { Venue } from '@/types/venue'

const moods = [
  {
    id: 'festif',
    label: 'Festif',
    dot: 'bg-mood-festif',
    active: 'border-pink text-pink',
  },
  { id: 'chill', label: 'Chill', dot: 'bg-mood-chill', active: 'border-cyan text-cyan' },
  {
    id: 'decouverte',
    label: 'Découverte',
    dot: 'bg-mood-decouverte',
    active: 'border-violet-bright text-violet-bright',
  },
  {
    id: 'afterwork',
    label: 'Afterwork',
    dot: 'bg-mood-afterwork',
    active: 'border-gold text-gold',
  },
] as const

// Tailwind v4 JIT ne détecte que les classes littérales.
const MOOD_DOT: Record<string, string> = {
  festif: 'bg-mood-festif',
  chill: 'bg-mood-chill',
  decouverte: 'bg-mood-decouverte',
  afterwork: 'bg-mood-afterwork',
}
const MOOD_LABEL: Record<string, string> = {
  festif: 'Festif',
  chill: 'Chill',
  decouverte: 'Découverte',
  afterwork: 'Afterwork',
}

const venues = ref<Venue[]>([])
const isLoading = ref(true)
const hasError = ref(false)
const activeMood = ref<string | null>(null)
const query = ref('')
const selectedSlug = ref<string | null>(null)

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  let list = venues.value
  if (activeMood.value) list = list.filter((v) => v.mood === activeMood.value)
  if (q) {
    list = list.filter(
      (v) =>
        v.name.toLowerCase().includes(q) ||
        v.city.toLowerCase().includes(q) ||
        v.address_line.toLowerCase().includes(q),
    )
  }
  return list
})

// Seuls les lieux géolocalisés vont sur la carte ; la liste montre tout le filtre.
const mappable = computed(() =>
  filtered.value.filter((v) => v.latitude !== null && v.longitude !== null),
)

function moodDotClass(mood: string | null): string {
  return (mood && MOOD_DOT[mood]) || 'bg-white/20'
}
function moodLabel(mood: string | null): string {
  return (mood && MOOD_LABEL[mood]) || 'Lieu'
}

function formatEventDate(iso: string): string {
  const d = new Date(iso)
  const day = d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
  const time = d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  return `${day} · ${time}`
}

async function load(): Promise<void> {
  isLoading.value = true
  hasError.value = false
  try {
    const result = await fetchVenues()
    venues.value = Array.isArray(result) ? result : []
  } catch {
    hasError.value = true
    venues.value = []
  } finally {
    isLoading.value = false
  }
}

function selectVenue(slug: string): void {
  selectedSlug.value = slug
  // Fait défiler la carte correspondante dans la liste latérale.
  nextTick(() => {
    document
      .querySelector(`[data-venue-slug="${slug}"]`)
      ?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
  })
}

function toggleMood(id: string): void {
  activeMood.value = activeMood.value === id ? null : id
  selectedSlug.value = null
}

onMounted(load)
</script>

<template>
  <div class="flex h-[calc(100dvh-65px)] flex-col overflow-hidden md:flex-row">
    <!-- Panneau : filtres + liste (sidebar desktop / bandeau bas mobile) -->
    <aside
      class="order-2 flex h-[44%] w-full shrink-0 flex-col border-t border-hairline bg-ink-2 md:order-1 md:h-full md:w-[380px] md:border-r md:border-t-0"
    >
      <div class="shrink-0 border-b border-hairline px-5 pb-4 pt-5">
        <div class="flex items-baseline justify-between">
          <h1 class="font-serif text-2xl italic text-text">Explorer la nuit</h1>
          <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
            {{ filtered.length }} {{ filtered.length > 1 ? 'lieux' : 'lieu' }}
          </span>
        </div>

        <form
          class="glass mt-4 flex items-center gap-2 rounded-full border border-hairline bg-glass py-1.5 pl-4 pr-1.5"
          @submit.prevent
        >
          <input
            v-model="query"
            type="search"
            placeholder="Bar, club, quartier…"
            aria-label="Rechercher un lieu"
            class="min-w-0 flex-1 bg-transparent text-sm text-text placeholder:text-text-3 focus:outline-none"
          />
        </form>

        <ul class="mt-3 flex flex-wrap gap-2">
          <li v-for="mood in moods" :key="mood.id">
            <button
              type="button"
              data-testid="explorer-mood-filter"
              :data-mood="mood.id"
              :aria-pressed="activeMood === mood.id"
              class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 font-mono text-[10px] uppercase tracking-[0.16em] transition"
              :class="
                activeMood === mood.id
                  ? mood.active
                  : 'border-hairline text-text-2 hover:border-hairline-bright hover:text-text'
              "
              @click="toggleMood(mood.id)"
            >
              <span class="h-2 w-2 rounded-full" :class="mood.dot" />
              {{ mood.label }}
            </button>
          </li>
        </ul>
      </div>

      <!-- Liste scrollable -->
      <div class="flex-1 overflow-y-auto px-3 py-3">
        <p
          v-if="isLoading"
          data-testid="explorer-loading"
          class="py-10 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
        >
          Chargement des lieux…
        </p>
        <p
          v-else-if="hasError"
          data-testid="explorer-error"
          class="py-10 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
        >
          Impossible de charger les lieux.
        </p>
        <p
          v-else-if="filtered.length === 0"
          data-testid="explorer-empty"
          class="py-10 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
        >
          Aucun lieu pour ce filtre.
        </p>

        <ul v-else class="flex flex-col gap-2">
          <li
            v-for="venue in filtered"
            :key="venue.id"
            data-testid="explorer-venue"
            :data-venue-slug="venue.slug"
          >
            <div
              class="glass rounded-2xl border bg-glass p-3 transition"
              :class="
                selectedSlug === venue.slug
                  ? 'border-hairline-bright bg-glass-strong'
                  : 'border-hairline hover:border-hairline-bright'
              "
            >
              <div class="flex items-start justify-between gap-2">
                <button
                  type="button"
                  class="min-w-0 flex-1 text-left"
                  :aria-pressed="selectedSlug === venue.slug"
                  @click="selectVenue(venue.slug)"
                >
                  <span
                    class="flex w-fit items-center gap-1.5 font-mono text-[9px] uppercase tracking-[0.16em] text-text-3"
                  >
                    <span
                      class="h-1.5 w-1.5 rounded-full"
                      :class="moodDotClass(venue.mood)"
                      aria-hidden="true"
                    />
                    {{ moodLabel(venue.mood) }}
                  </span>
                  <span class="mt-1 block truncate font-serif text-base italic text-text">
                    {{ venue.name }}
                  </span>
                  <span class="block truncate text-xs text-text-3">{{
                    venue.address_line
                  }}</span>
                </button>
                <RouterLink
                  :to="`/venues/${venue.slug}`"
                  class="shrink-0 rounded-full border border-hairline px-2.5 py-1 font-mono text-[9px] uppercase tracking-[0.16em] text-text-2 transition hover:border-hairline-bright hover:text-text"
                >
                  Fiche ↗
                </RouterLink>
              </div>
              <p
                v-if="venue.next_event"
                class="mt-2 flex items-center gap-2 border-t border-hairline/70 pt-2"
              >
                <span
                  class="shrink-0 rounded-md bg-pink/15 px-1.5 py-0.5 font-mono text-[9px] uppercase tracking-[0.1em] text-pink-bright"
                >
                  {{ formatEventDate(venue.next_event.starts_at) }}
                </span>
                <span class="truncate text-[11px] text-text-2">{{
                  venue.next_event.title
                }}</span>
              </p>
            </div>
          </li>
        </ul>
      </div>
    </aside>

    <!-- Carte -->
    <div class="relative order-1 h-[56%] w-full md:order-2 md:h-full md:flex-1">
      <VenuesMap
        :venues="mappable"
        :selected-slug="selectedSlug"
        class="absolute inset-0"
        @select="selectVenue"
      />
    </div>
  </div>
</template>
