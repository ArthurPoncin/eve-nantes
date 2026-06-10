<script setup lang="ts">
import { ref } from 'vue'
import { generateSoiree, shareSoiree } from '@/api/soiree'
import type { Soiree } from '@/types/soiree'

const moods = [
  {
    id: 'festif',
    label: 'Festif',
    desc: 'Clubs, DJ, dancefloor',
    active: 'border-pink text-pink glow-pink',
    dot: 'bg-mood-festif',
  },
  {
    id: 'chill',
    label: 'Chill',
    desc: 'Bars cosy, douceur',
    active: 'border-cyan text-cyan glow-cyan',
    dot: 'bg-mood-chill',
  },
  {
    id: 'decouverte',
    label: 'Découverte',
    desc: 'Scènes, curiosités',
    active: 'border-violet-bright text-violet-bright glow-violet',
    dot: 'bg-mood-decouverte',
  },
  {
    id: 'afterwork',
    label: 'Afterwork',
    desc: 'Apéro, before',
    active: 'border-gold text-gold glow-gold',
    dot: 'bg-mood-afterwork',
  },
] as const

const MOOD_LABEL: Record<string, string> = {
  festif: 'Festif',
  chill: 'Chill',
  decouverte: 'Découverte',
  afterwork: 'Afterwork',
}
const MOOD_DOT: Record<string, string> = {
  festif: 'bg-mood-festif',
  chill: 'bg-mood-chill',
  decouverte: 'bg-mood-decouverte',
  afterwork: 'bg-mood-afterwork',
}

const selectedMood = ref<string | null>(null)
const soiree = ref<Soiree | null>(null)
const isLoading = ref(false)
const hasError = ref(false)
const notFound = ref(false)
const shareOpen = ref(false)
const shareEmail = ref('')
const shareStatus = ref<'idle' | 'sending' | 'sent' | 'error'>('idle')

async function compose(mood: string): Promise<void> {
  selectedMood.value = mood
  isLoading.value = true
  hasError.value = false
  notFound.value = false
  soiree.value = null
  resetShare()
  try {
    soiree.value = await generateSoiree(mood)
  } catch (error: unknown) {
    const status = (error as { response?: { status?: number } })?.response?.status
    if (status === 404) notFound.value = true
    else hasError.value = true
  } finally {
    isLoading.value = false
  }
}

function reset(): void {
  selectedMood.value = null
  soiree.value = null
  hasError.value = false
  notFound.value = false
  resetShare()
}

function resetShare(): void {
  shareOpen.value = false
  shareEmail.value = ''
  shareStatus.value = 'idle'
}

async function submitShare(): Promise<void> {
  if (!soiree.value || !shareEmail.value.trim()) return
  shareStatus.value = 'sending'
  try {
    await shareSoiree({
      email: shareEmail.value.trim(),
      mood: soiree.value.mood,
      venue_id: soiree.value.venue.id,
      event_id: soiree.value.event?.id ?? null,
      narrative: soiree.value.narrative,
      weather: soiree.value.weather,
    })
    shareStatus.value = 'sent'
  } catch {
    shareStatus.value = 'error'
  }
}

function moodLabel(mood: string | null): string {
  return (mood && MOOD_LABEL[mood]) || ''
}
function moodDot(mood: string | null): string {
  return (mood && MOOD_DOT[mood]) || 'bg-white/20'
}
function formatEvent(iso: string): string {
  const d = new Date(iso)
  const date = d.toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
  const time = d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  return `${date} · ${time}`
}
function weatherIcon(icon: string): string {
  return `https://openweathermap.org/img/wn/${icon}@2x.png`
}
</script>

<template>
  <main
    class="mx-auto flex w-full max-w-2xl flex-col items-center gap-8 px-6 pb-24 pt-16 text-center"
  >
    <div class="flex flex-col items-center gap-3">
      <p class="font-mono text-[11px] uppercase tracking-[0.3em] text-text-3">
        Nantes · ce soir
      </p>
      <h1 class="font-serif text-4xl italic leading-tight text-text md:text-5xl">
        Compose <span class="text-pink">ta soirée</span>
      </h1>
      <p class="max-w-md text-base text-text-2">
        Choisis ton humeur, on s'occupe du reste — un lieu, un événement, la météo et une
        suggestion rien que pour toi.
      </p>
    </div>

    <!-- Sélection d'ambiance -->
    <ul v-if="!soiree && !isLoading" class="grid w-full grid-cols-2 gap-3">
      <li v-for="mood in moods" :key="mood.id">
        <button
          type="button"
          data-testid="soiree-mood"
          :data-mood="mood.id"
          class="glass flex h-full w-full flex-col items-start gap-1 rounded-2xl border border-hairline bg-glass p-4 text-left transition hover:border-hairline-bright"
          :class="{ [mood.active]: selectedMood === mood.id }"
          @click="compose(mood.id)"
        >
          <span
            class="flex items-center gap-2 font-mono text-[11px] uppercase tracking-[0.16em] text-text"
          >
            <span class="h-2 w-2 rounded-full" :class="mood.dot" />
            {{ mood.label }}
          </span>
          <span class="text-xs text-text-3">{{ mood.desc }}</span>
        </button>
      </li>
    </ul>

    <p
      v-if="isLoading"
      data-testid="soiree-loading"
      class="py-10 font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      On compose ta soirée…
    </p>

    <div
      v-else-if="notFound"
      data-testid="soiree-empty"
      class="flex flex-col items-center gap-4 py-8"
    >
      <p class="font-mono text-[11px] uppercase tracking-[0.18em] text-text-3">
        Aucun lieu pour cette ambiance ce soir.
      </p>
      <button
        type="button"
        class="rounded-full border border-hairline px-5 py-2.5 font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:text-text"
        @click="reset"
      >
        Choisir une autre ambiance
      </button>
    </div>

    <div
      v-else-if="hasError"
      data-testid="soiree-error"
      class="flex flex-col items-center gap-4 py-8"
    >
      <p class="font-mono text-[11px] uppercase tracking-[0.18em] text-text-3">
        Impossible de composer la soirée.
      </p>
      <button
        type="button"
        class="rounded-full border border-hairline px-5 py-2.5 font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:text-text"
        @click="selectedMood && compose(selectedMood)"
      >
        Réessayer
      </button>
    </div>

    <!-- Résultat -->
    <article
      v-else-if="soiree"
      data-testid="soiree-result"
      class="glass-strong relative w-full overflow-hidden rounded-3xl border border-hairline bg-glass-strong p-6 text-left sm:p-8"
    >
      <p class="font-mono text-[10px] uppercase tracking-[0.22em] text-text-3">
        {{ moodLabel(soiree.mood) }} · ce soir à {{ soiree.venue.city }}
      </p>

      <blockquote
        data-testid="soiree-narrative"
        class="mt-3 font-serif text-2xl italic leading-snug text-text sm:text-3xl"
      >
        « {{ soiree.narrative }} »
      </blockquote>

      <div class="mt-6 flex flex-col gap-4 border-t border-hairline pt-5">
        <div class="flex items-start justify-between gap-3">
          <div class="flex min-w-0 flex-col">
            <span
              class="flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.16em] text-text-3"
            >
              <span class="h-1.5 w-1.5 rounded-full" :class="moodDot(soiree.mood)" />
              Le spot
            </span>
            <RouterLink
              :to="`/venues/${soiree.venue.slug}`"
              data-testid="soiree-venue-link"
              class="mt-0.5 font-serif text-2xl italic text-text transition hover:text-pink-bright"
            >
              {{ soiree.venue.name }} →
            </RouterLink>
            <span class="text-sm text-text-3">{{ soiree.venue.address_line }}</span>
          </div>
          <div
            class="flex shrink-0 items-center gap-1.5 rounded-full border border-hairline px-3 py-1.5"
          >
            <img
              :src="weatherIcon(soiree.weather.icon)"
              :alt="soiree.weather.condition"
              class="h-6 w-6"
              width="24"
              height="24"
            />
            <span class="font-serif text-lg italic text-text"
              >{{ Math.round(soiree.weather.temp) }}°</span
            >
          </div>
        </div>

        <div v-if="soiree.event" class="rounded-2xl border border-pink/30 bg-pink/5 p-4">
          <span
            class="font-mono text-[10px] uppercase tracking-[0.16em] text-pink-bright"
          >
            ✦ L'événement
          </span>
          <p class="mt-1 font-serif text-lg italic text-text">{{ soiree.event.title }}</p>
          <p class="mt-0.5 font-mono text-[11px] capitalize text-text-3">
            {{ formatEvent(soiree.event.starts_at) }}
          </p>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap gap-3">
        <button
          type="button"
          data-testid="soiree-regenerate"
          class="glow-pink rounded-full bg-pink px-5 py-3 font-mono text-[11px] uppercase tracking-[0.18em] text-white transition hover:bg-pink-bright"
          @click="compose(soiree.mood)"
        >
          Régénérer
        </button>
        <button
          type="button"
          data-testid="soiree-share-toggle"
          class="rounded-full border border-cyan/40 px-5 py-3 font-mono text-[11px] uppercase tracking-[0.18em] text-cyan transition hover:bg-cyan/10"
          @click="shareOpen = !shareOpen"
        >
          Partager par email
        </button>
        <button
          type="button"
          class="rounded-full border border-hairline px-5 py-3 font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:border-hairline-bright hover:text-text"
          @click="reset"
        >
          Changer d'ambiance
        </button>
      </div>

      <form
        v-if="shareOpen"
        data-testid="soiree-share-form"
        class="mt-4 flex flex-col gap-2 sm:flex-row"
        @submit.prevent="submitShare"
      >
        <input
          v-model="shareEmail"
          type="email"
          required
          placeholder="email d'un ami…"
          aria-label="Email du destinataire"
          class="glass min-w-0 flex-1 rounded-full border border-hairline bg-glass px-4 py-2.5 text-sm text-text placeholder:text-text-3 focus:outline-none"
        />
        <button
          type="submit"
          data-testid="soiree-share-submit"
          :disabled="shareStatus === 'sending'"
          class="shrink-0 rounded-full bg-pink px-5 py-2.5 font-mono text-[11px] uppercase tracking-[0.18em] text-white transition hover:bg-pink-bright disabled:opacity-50"
        >
          {{ shareStatus === 'sending' ? 'Envoi…' : 'Envoyer' }}
        </button>
      </form>
      <p
        v-if="shareStatus === 'sent'"
        data-testid="soiree-share-sent"
        class="mt-2 font-mono text-[11px] uppercase tracking-[0.16em] text-cyan"
      >
        Envoyé ✓ — ton ami·e reçoit la soirée par email.
      </p>
      <p
        v-else-if="shareStatus === 'error'"
        data-testid="soiree-share-error"
        class="mt-2 font-mono text-[11px] uppercase tracking-[0.16em] text-text-3"
      >
        Échec de l'envoi, réessaie.
      </p>
    </article>
  </main>
</template>
