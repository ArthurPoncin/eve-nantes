<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { fetchViree } from '@/api/virees'
import { useVireeStore } from '@/stores/viree'
import VireeMap from '@/components/VireeMap.vue'
import type { Viree } from '@/types/viree'

const route = useRoute()
const vireeStore = useVireeStore()

// Mêmes maps statiques que la fiche lieu (Tailwind JIT : classes littérales).
const MOOD_CHIP: Record<string, string> = {
  festif: 'border-pink/40 text-pink',
  chill: 'border-cyan/40 text-cyan',
  decouverte: 'border-violet-bright/40 text-violet-bright',
  afterwork: 'border-gold/40 text-gold',
}
const MOOD_LABEL: Record<string, string> = {
  festif: 'Festif',
  chill: 'Chill',
  decouverte: 'Découverte',
  afterwork: 'Afterwork',
}

const viree = ref<Viree | null>(null)
const isLoading = ref(true)
const hasError = ref(false)
const isClosing = ref(false)

// La virée affichée est-elle la mienne, encore en cours ? (bouton Terminer)
const isMineAndActive = computed(
  () =>
    viree.value !== null &&
    viree.value.status === 'en_cours' &&
    vireeStore.current?.public_id === viree.value.public_id,
)

const dateLabel = computed(() => {
  if (!viree.value) return ''
  return new Date(viree.value.started_at).toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
})

const distanceLabel = computed(() => {
  const meters = viree.value?.stats.distance_m
  if (meters === null || meters === undefined) return '—'
  return `${(meters / 1000).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} km`
})

const durationLabel = computed(() => {
  const minutes = viree.value?.stats.duration_min ?? 0
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60
  return hours > 0 ? `${hours} h ${String(rest).padStart(2, '0')}` : `${rest} min`
})

function timeLabel(isoDate: string): string {
  return new Date(isoDate).toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function moodChipClass(mood: string): string {
  return MOOD_CHIP[mood] ?? 'border-hairline text-text-2'
}
function moodLabel(mood: string): string {
  return MOOD_LABEL[mood] ?? mood
}

async function onClose(): Promise<void> {
  if (isClosing.value) return
  isClosing.value = true
  try {
    viree.value = await vireeStore.close()
  } catch {
    // Clôture impossible : l'état affiché reste « en cours ».
  } finally {
    isClosing.value = false
  }
}

onMounted(async () => {
  const param = route.params.publicId
  const publicId = (Array.isArray(param) ? param[0] : param) ?? ''
  try {
    viree.value = await fetchViree(publicId)
  } catch {
    hasError.value = true
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <main class="mx-auto flex w-full max-w-2xl flex-col gap-5 px-4 pb-24 pt-6 sm:px-6">
    <p
      v-if="isLoading"
      data-testid="viree-loading"
      class="py-20 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Chargement de la virée…
    </p>
    <p
      v-else-if="hasError || viree === null"
      data-testid="viree-error"
      class="py-20 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Virée introuvable.
    </p>

    <template v-else>
      <!-- Hero -->
      <section
        class="glass-strong relative overflow-hidden rounded-3xl border border-hairline bg-glass-strong p-6 sm:p-8"
      >
        <div
          aria-hidden="true"
          class="pointer-events-none absolute -right-16 -top-24 h-56 w-56 rounded-full bg-pink opacity-25 blur-3xl"
        />
        <p class="relative font-mono text-[11px] uppercase tracking-[0.22em] text-text-3">
          {{ viree.status === 'en_cours' ? 'Virée en cours' : 'Récap de nuit' }}
        </p>
        <h1
          data-testid="viree-title"
          class="relative mt-2 font-serif text-4xl italic leading-tight text-text sm:text-5xl"
        >
          Virée du {{ dateLabel }}
        </h1>
        <div class="relative mt-5 flex flex-wrap items-center gap-2">
          <span
            v-for="mood in viree.stats.moods"
            :key="mood"
            class="rounded-full border px-3 py-1 font-mono text-[10px] uppercase tracking-[0.16em]"
            :class="moodChipClass(mood)"
          >
            {{ moodLabel(mood) }}
          </span>
          <span
            v-if="viree.weather"
            class="rounded-full border border-hairline px-3 py-1 font-mono text-[10px] uppercase tracking-[0.16em] text-text-2"
          >
            {{ viree.weather.condition }} · {{ Math.round(viree.weather.temp) }}°
          </span>
        </div>
      </section>

      <!-- Tracé -->
      <VireeMap :checkins="viree.checkins" />

      <!-- Stats -->
      <section class="grid grid-cols-3 gap-3" data-testid="viree-stats">
        <div
          class="glass flex flex-col rounded-2xl border border-hairline bg-glass px-4 py-3"
        >
          <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
            Lieux
          </span>
          <span class="font-serif text-3xl italic text-text">{{
            viree.stats.venues
          }}</span>
        </div>
        <div
          class="glass flex flex-col rounded-2xl border border-hairline bg-glass px-4 py-3"
        >
          <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
            Distance
          </span>
          <span class="font-serif text-3xl italic text-text">{{ distanceLabel }}</span>
        </div>
        <div
          class="glass flex flex-col rounded-2xl border border-hairline bg-glass px-4 py-3"
        >
          <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
            Durée
          </span>
          <span class="font-serif text-3xl italic text-text">{{ durationLabel }}</span>
        </div>
      </section>

      <!-- Narration IA -->
      <blockquote
        v-if="viree.narrative"
        data-testid="viree-narrative"
        class="glass rounded-2xl border border-hairline bg-glass px-6 py-5 font-serif text-xl italic leading-relaxed text-text"
      >
        {{ viree.narrative }}
      </blockquote>

      <!-- Timeline des étapes -->
      <section class="flex flex-col gap-3">
        <h2 class="font-serif text-2xl italic text-text">Étapes</h2>
        <ol class="flex flex-col gap-2">
          <li
            v-for="(checkin, index) in viree.checkins"
            :key="checkin.id"
            data-testid="viree-checkin"
            class="glass flex items-center gap-4 rounded-2xl border border-hairline bg-glass px-4 py-3"
          >
            <span
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-hairline font-mono text-[11px] text-text-2"
            >
              {{ index + 1 }}
            </span>
            <RouterLink
              :to="`/venues/${checkin.venue.slug}`"
              class="flex-1 font-serif text-lg italic text-text transition hover:text-pink"
            >
              {{ checkin.venue.name }}
            </RouterLink>
            <span class="font-mono text-[11px] text-text-3">
              {{ timeLabel(checkin.happened_at) }}
            </span>
          </li>
        </ol>
      </section>

      <!-- Ma virée encore en cours : clôture possible depuis le récap -->
      <button
        v-if="isMineAndActive"
        type="button"
        data-testid="viree-close"
        class="glow-pink w-full rounded-full bg-pink px-6 py-3.5 font-mono text-[11px] uppercase tracking-[0.18em] text-white transition hover:bg-pink-bright"
        :disabled="isClosing"
        @click="onClose"
      >
        Terminer ma virée
      </button>
    </template>
  </main>
</template>
