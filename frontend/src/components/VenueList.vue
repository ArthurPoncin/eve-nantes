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
      </RouterLink>

      <FavoriteButton :venue="venue" class="absolute right-2 top-2 z-10" />
    </li>
  </ul>
</template>
