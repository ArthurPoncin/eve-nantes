<script setup lang="ts">
import FavoriteButton from './FavoriteButton.vue'
import type { Venue } from '@/types/venue'

defineProps<{ venues: Venue[] }>()

// Tailwind v4 JIT only detects full literal class strings, so dynamic
// `bg-mood-${mood}` names are never generated. Map them statically here.
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
const MOOD_LABEL: Record<string, string> = {
  festif: 'Festif',
  chill: 'Chill',
  decouverte: 'Découverte',
  afterwork: 'Afterwork',
}

const FALLBACK_DOT_CLASS = 'bg-white/20'

function moodDotClass(mood: string | null): string {
  return (mood && MOOD_DOT[mood]) || FALLBACK_DOT_CLASS
}
function moodChipClass(mood: string | null): string {
  return (mood && MOOD_CHIP[mood]) || 'border-hairline text-text-2'
}
function moodLabel(mood: string | null): string {
  return (mood && MOOD_LABEL[mood]) || 'Lieu'
}

// « 14 juin · 21:00 » — date courte du prochain événement pour la carte.
function formatEventDate(iso: string): string {
  const d = new Date(iso)
  const day = d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
  const time = d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  return `${day} · ${time}`
}
</script>

<template>
  <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <li
      v-for="venue in venues"
      :key="venue.id"
      data-testid="venue-item"
      class="group relative glass rounded-2xl border border-hairline bg-glass transition hover:border-hairline-bright"
    >
      <RouterLink
        :to="`/venues/${venue.slug}`"
        class="flex h-full flex-col gap-3 p-4 pr-12 text-left"
      >
        <span
          class="flex w-fit items-center gap-1.5 rounded-full border px-2.5 py-1 font-mono text-[9px] uppercase tracking-[0.16em]"
          :class="moodChipClass(venue.mood)"
        >
          <span
            data-testid="venue-mood-dot"
            class="h-1.5 w-1.5 rounded-full"
            :class="moodDotClass(venue.mood)"
            aria-hidden="true"
          />
          {{ moodLabel(venue.mood) }}
        </span>

        <div class="mt-auto flex flex-col gap-0.5">
          <span class="font-serif text-lg italic leading-tight text-text">
            {{ venue.name }}
          </span>
          <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">
            {{ venue.city }}
          </span>
          <span class="line-clamp-1 text-sm text-text-2">{{ venue.address_line }}</span>
        </div>

        <div
          v-if="venue.next_event"
          data-testid="venue-next-event"
          class="flex items-center gap-2 border-t border-hairline/70 pt-2.5"
        >
          <span
            class="shrink-0 rounded-md bg-pink/15 px-1.5 py-1 font-mono text-[9px] uppercase tracking-[0.1em] text-pink-bright"
          >
            {{ formatEventDate(venue.next_event.starts_at) }}
          </span>
          <span class="line-clamp-1 text-[11px] text-text-2">{{ venue.next_event.title }}</span>
        </div>
      </RouterLink>

      <FavoriteButton :venue="venue" class="absolute right-2 top-2 z-10" />
    </li>
  </ul>
</template>
