<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { fetchVenue } from '@/api/venues'
import type { VenueDetail } from '@/types/venue'

const route = useRoute()

// Tailwind v4 JIT only detects full literal class strings, so dynamic
// `bg-mood-${mood}` names are never generated. Map them statically here.
const MOOD_CLASS: Record<string, string> = {
  festif: 'bg-mood-festif',
  chill: 'bg-mood-chill',
  decouverte: 'bg-mood-decouverte',
  afterwork: 'bg-mood-afterwork',
}

const FALLBACK_DOT_CLASS = 'bg-white/20'

function moodDotClass(mood: string | null): string {
  return (mood && MOOD_CLASS[mood]) || FALLBACK_DOT_CLASS
}

function formatDate(startsAt: string): string {
  return new Date(startsAt).toLocaleString('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

function formatPrice(priceCents: number): string {
  if (priceCents === 0) {
    return 'Gratuit'
  }
  return `${priceCents / 100} €`
}

const venue = ref<VenueDetail | null>(null)
const isLoading = ref(true)
const hasError = ref(false)

async function loadVenue() {
  isLoading.value = true
  hasError.value = false
  const slugParam = route.params.slug
  const slug = (Array.isArray(slugParam) ? slugParam[0] : slugParam) ?? ''
  try {
    venue.value = await fetchVenue(slug)
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
  <main class="mx-auto flex min-h-[80vh] w-full max-w-2xl flex-col gap-6 px-6 py-12">
    <p
      v-if="isLoading"
      data-testid="venue-detail-loading"
      class="font-mono text-xs uppercase tracking-widest text-ink-muted"
    >
      Chargement du lieu…
    </p>
    <p
      v-else-if="hasError || venue === null"
      data-testid="venue-detail-error"
      class="font-mono text-xs uppercase tracking-widest text-ink-muted"
    >
      Lieu introuvable.
    </p>

    <template v-else>
      <section class="rounded-2xl border border-white/10 bg-white/5 p-8">
        <div class="flex items-center gap-3">
          <span
            data-testid="venue-detail-mood-dot"
            class="h-2.5 w-2.5 shrink-0 rounded-full"
            :class="moodDotClass(venue.mood)"
            aria-hidden="true"
          />
          <h1
            data-testid="venue-detail-name"
            class="font-serif text-4xl italic text-ink-primary"
          >
            {{ venue.name }}
          </h1>
        </div>

        <dl class="mt-6 flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">
              Ville
            </dt>
            <dd class="text-ink-primary">{{ venue.city }}</dd>
          </div>
          <div class="flex flex-col gap-1">
            <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">
              Adresse
            </dt>
            <dd class="text-ink-primary">{{ venue.address_line }}</dd>
          </div>
          <div v-if="venue.capacity !== null" class="flex flex-col gap-1">
            <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">
              Capacité
            </dt>
            <dd class="text-ink-primary">{{ venue.capacity }}</dd>
          </div>
        </dl>
      </section>

      <section class="flex flex-col gap-4">
        <h2 class="font-serif text-2xl italic text-ink-primary">À l'affiche</h2>

        <p
          v-if="venue.events.length === 0"
          data-testid="venue-events-empty"
          class="font-mono text-xs uppercase tracking-widest text-ink-muted"
        >
          Aucun événement à venir.
        </p>

        <ul v-else class="flex flex-col gap-3">
          <li
            v-for="event in venue.events"
            :key="event.id"
            data-testid="venue-event"
            class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3"
          >
            <p class="font-serif text-lg italic text-ink-primary">
              {{ event.title }}
            </p>
            <p class="mt-1 font-mono text-[10px] uppercase tracking-widest text-ink-muted">
              {{ formatDate(event.starts_at) }}
            </p>
            <p class="mt-1 text-sm text-ink-muted">
              {{ formatPrice(event.price_cents) }}
            </p>
          </li>
        </ul>
      </section>
    </template>
  </main>
</template>
