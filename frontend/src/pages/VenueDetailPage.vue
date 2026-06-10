<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { fetchVenue } from '@/api/venues'
import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'
import FavoriteButton from '@/components/FavoriteButton.vue'
import ReviewsSection from '@/components/ReviewsSection.vue'
import TransportWidget from '@/components/TransportWidget.vue'
import WeatherWidget from '@/components/WeatherWidget.vue'
import VenueMap from '@/components/VenueMap.vue'
import type { EventSummary } from '@/types/event'
import type { VenueDetail } from '@/types/venue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const favorites = useFavoritesStore()

// Tailwind v4 JIT ne détecte que les classes littérales : on mappe statiquement
// chaque ambiance vers ses utilitaires (pastille, puce, halo, libellé).
const MOOD_DOT: Record<string, string> = {
  festif: 'bg-mood-festif',
  chill: 'bg-mood-chill',
  decouverte: 'bg-mood-decouverte',
  afterwork: 'bg-mood-afterwork',
}
const MOOD_CHIP: Record<string, string> = {
  festif: 'border-pink/40 text-pink',
  chill: 'border-cyan/40 text-cyan',
  decouverte: 'border-violet-bright/40 text-violet-bright',
  afterwork: 'border-gold/40 text-gold',
}
const MOOD_GLOW: Record<string, string> = {
  festif: 'bg-pink',
  chill: 'bg-cyan',
  decouverte: 'bg-violet-bright',
  afterwork: 'bg-gold',
}
const MOOD_LABEL: Record<string, string> = {
  festif: 'Festif',
  chill: 'Chill',
  decouverte: 'Découverte',
  afterwork: 'Afterwork',
}

function moodDotClass(mood: string | null): string {
  return (mood && MOOD_DOT[mood]) || 'bg-white/20'
}
function moodChipClass(mood: string | null): string {
  return (mood && MOOD_CHIP[mood]) || 'border-hairline text-text-2'
}
function moodGlowClass(mood: string | null): string {
  return (mood && MOOD_GLOW[mood]) || 'bg-violet'
}
// Bordure teintée pour mettre en avant le prochain événement (carte « featured »).
const MOOD_BORDER: Record<string, string> = {
  festif: 'border-pink/40',
  chill: 'border-cyan/40',
  decouverte: 'border-violet-bright/40',
  afterwork: 'border-gold/40',
}

function moodLabel(mood: string | null): string {
  return (mood && MOOD_LABEL[mood]) || ''
}
function moodBorderClass(mood: string | null): string {
  return (mood && MOOD_BORDER[mood]) || 'border-hairline'
}

const venue = ref<VenueDetail | null>(null)
const isLoading = ref(true)
const hasError = ref(false)
const shareCopied = ref(false)

const categoryLine = computed(() => {
  if (!venue.value) return ''
  const label = moodLabel(venue.value.mood)
  return [venue.value.city, label].filter(Boolean).join(' · ')
})

const fullAddress = computed(() => {
  if (!venue.value) return ''
  const v = venue.value
  return `${v.address_line} · ${v.postal_code} ${v.city}`.trim()
})

const isFavorite = computed(() =>
  venue.value ? favorites.isFavorite(venue.value.slug) : false,
)

function formatDay(startsAt: string): string {
  return new Date(startsAt).toLocaleDateString('fr-FR', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  })
}

function formatRange(event: EventSummary): string {
  const start = new Date(event.starts_at)
  const date = start.toLocaleDateString('fr-FR', { dateStyle: 'full' })
  const startTime = start.toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  })
  const endTime = new Date(event.ends_at).toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  })
  return `${date} · ${startTime} — ${endTime}`
}

function formatPrice(priceCents: number): string {
  return priceCents === 0 ? 'Gratuit' : `${priceCents / 100} €`
}

function goBack(): void {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/')
  }
}

function toggleFavorite(): void {
  if (venue.value) {
    favorites.toggle(venue.value).catch(() => {})
  }
}

async function share(): Promise<void> {
  const url = window.location.href
  try {
    if (typeof navigator.share === 'function') {
      await navigator.share({ title: venue.value?.name, url })
      return
    }
    await navigator.clipboard.writeText(url)
    shareCopied.value = true
    window.setTimeout(() => {
      shareCopied.value = false
    }, 2000)
  } catch {
    // Partage annulé ou indisponible : on ne fait rien.
  }
}

async function loadVenue(): Promise<void> {
  isLoading.value = true
  hasError.value = false
  const slugParam = route.params.slug
  const slug = (Array.isArray(slugParam) ? slugParam[0] : slugParam) ?? ''
  try {
    venue.value = await fetchVenue(slug)
    // Entrée directe par URL : on charge les favoris pour que le cœur soit juste.
    if (auth.isAuthenticated && !favorites.loaded) {
      favorites.load().catch(() => {})
    }
  } catch {
    hasError.value = true
    venue.value = null
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadVenue()
})
</script>

<template>
  <main class="mx-auto flex w-full max-w-2xl flex-col gap-5 px-4 pb-24 pt-6 sm:px-6">
    <p
      v-if="isLoading"
      data-testid="venue-detail-loading"
      class="py-20 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Chargement du lieu…
    </p>
    <p
      v-else-if="hasError || venue === null"
      data-testid="venue-detail-error"
      class="py-20 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Lieu introuvable.
    </p>

    <template v-else>
      <!-- Hero -->
      <section
        class="glass-strong relative overflow-hidden rounded-3xl border border-hairline bg-glass-strong p-6 sm:p-8"
      >
        <div
          aria-hidden="true"
          class="pointer-events-none absolute -right-16 -top-24 h-56 w-56 rounded-full opacity-30 blur-3xl"
          :class="moodGlowClass(venue.mood)"
        />

        <div class="relative flex items-center justify-between">
          <button
            type="button"
            data-testid="venue-back"
            aria-label="Retour"
            class="glass flex h-10 w-10 items-center justify-center rounded-full border border-hairline bg-glass text-text-2 transition hover:border-hairline-bright hover:text-text"
            @click="goBack"
          >
            ←
          </button>
          <div class="flex items-center gap-1">
            <button
              type="button"
              data-testid="venue-share"
              :aria-label="shareCopied ? 'Lien copié' : 'Partager'"
              class="glass flex h-10 w-10 items-center justify-center rounded-full border border-hairline bg-glass text-text-2 transition hover:border-hairline-bright hover:text-text"
              @click="share"
            >
              ↗
            </button>
            <FavoriteButton :venue="venue" />
          </div>
        </div>

        <p
          class="relative mt-6 font-mono text-[11px] uppercase tracking-[0.22em] text-text-3"
        >
          {{ categoryLine }}
        </p>
        <h1
          data-testid="venue-detail-name"
          class="relative mt-2 font-serif text-4xl italic leading-tight text-text sm:text-5xl"
        >
          {{ venue.name }}
        </h1>

        <div class="relative mt-5 flex flex-wrap items-center gap-2">
          <span
            v-if="venue.mood"
            class="flex items-center gap-2 rounded-full border px-3 py-1 font-mono text-[10px] uppercase tracking-[0.16em]"
            :class="moodChipClass(venue.mood)"
          >
            <span
              data-testid="venue-detail-mood-dot"
              class="h-1.5 w-1.5 rounded-full"
              :class="moodDotClass(venue.mood)"
              aria-hidden="true"
            />
            {{ moodLabel(venue.mood) }}
          </span>
          <span
            v-if="venue.capacity !== null"
            class="rounded-full border border-hairline px-3 py-1 font-mono text-[10px] uppercase tracking-[0.16em] text-text-2"
          >
            Capacité {{ venue.capacity }}
          </span>
          <span
            class="rounded-full border border-hairline px-3 py-1 font-mono text-[10px] uppercase tracking-[0.16em] text-text-2"
          >
            📍 {{ venue.postal_code }} {{ venue.city }}
          </span>
        </div>
      </section>

      <!-- Appel à l'action -->
      <div class="flex items-center gap-3">
        <button
          v-if="auth.isAuthenticated"
          type="button"
          data-testid="venue-cta-favorite"
          class="flex-1 rounded-full px-6 py-3.5 text-center font-mono text-[11px] uppercase tracking-[0.18em] transition"
          :class="
            isFavorite
              ? 'border border-pink/50 text-pink hover:bg-pink/10'
              : 'glow-pink bg-pink text-white hover:bg-pink-bright'
          "
          @click="toggleFavorite"
        >
          {{ isFavorite ? 'Dans tes favoris ✓' : 'Ajouter à mes favoris' }}
        </button>
        <RouterLink
          v-else
          :to="`/login?redirect=/venues/${venue.slug}`"
          data-testid="venue-cta-login"
          class="glow-pink flex-1 rounded-full bg-pink px-6 py-3.5 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-white transition hover:bg-pink-bright"
        >
          Se connecter pour sauvegarder
        </RouterLink>
      </div>

      <!-- À l'affiche -->
      <section class="flex flex-col gap-3">
        <div class="flex items-baseline justify-between">
          <h2 class="font-serif text-2xl italic text-text">À l'affiche</h2>
          <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
            {{ venue.events.length }}
            {{ venue.events.length > 1 ? 'événements' : 'événement' }}
          </span>
        </div>

        <p
          v-if="venue.events.length === 0"
          data-testid="venue-events-empty"
          class="rounded-2xl border border-hairline bg-glass px-5 py-8 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
        >
          Aucun événement à venir.
        </p>

        <ul v-else class="flex flex-col gap-3">
          <li
            v-for="(event, index) in venue.events"
            :key="event.id"
            data-testid="venue-event"
            class="glass rounded-2xl bg-glass p-4 transition"
            :class="
              index === 0
                ? `border ${moodBorderClass(venue.mood)}`
                : 'border border-hairline hover:border-hairline-bright'
            "
          >
            <div class="flex items-center justify-between gap-3">
              <span
                v-if="index === 0"
                class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.16em]"
                :class="moodChipClass(venue.mood)"
              >
                ✦ Prochainement
              </span>
              <span
                v-else
                class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3"
              >
                {{ formatDay(event.starts_at) }}
              </span>
              <span
                class="shrink-0 rounded-full border border-hairline px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.16em] text-text-2"
              >
                {{ formatPrice(event.price_cents) }}
              </span>
            </div>
            <p
              class="mt-2 font-serif italic text-text"
              :class="index === 0 ? 'text-2xl' : 'text-lg'"
            >
              {{ event.title }}
            </p>
            <p class="mt-0.5 font-mono text-[11px] text-text-3">
              {{ formatRange(event) }}
            </p>
            <p v-if="event.description" class="mt-2 line-clamp-3 text-sm text-text-2">
              {{ event.description }}
            </p>
          </li>
        </ul>
      </section>

      <!-- Infos pratiques : carte + météo (+ capacité) -->
      <section class="flex flex-col gap-3">
        <VenueMap
          :latitude="venue.latitude"
          :longitude="venue.longitude"
          :name="venue.name"
          :address="fullAddress"
        />
        <TransportWidget :slug="venue.slug" />
        <div class="grid gap-3" :class="venue.capacity !== null ? 'sm:grid-cols-2' : ''">
          <WeatherWidget />
          <div
            v-if="venue.capacity !== null"
            class="glass flex flex-col justify-center rounded-2xl border border-hairline bg-glass px-5 py-4"
          >
            <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
              Capacité
            </span>
            <span class="font-serif text-3xl italic text-text">{{ venue.capacity }}</span>
            <span class="text-xs text-text-3">personnes</span>
          </div>
        </div>
      </section>

      <!-- Avis de la communauté (note moyenne + commentaires) -->
      <ReviewsSection :slug="venue.slug" />

      <span v-if="shareCopied" aria-live="polite" class="sr-only">Lien copié</span>
    </template>
  </main>
</template>
