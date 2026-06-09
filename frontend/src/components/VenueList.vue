<script setup lang="ts">
import FavoriteButton from './FavoriteButton.vue'
import type { Venue } from '@/types/venue'

defineProps<{ venues: Venue[] }>()

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
</script>

<template>
  <ul class="flex w-full flex-col gap-3">
    <li
      v-for="venue in venues"
      :key="venue.id"
      data-testid="venue-item"
      class="glass flex items-center gap-2 rounded-2xl border border-hairline bg-glass pr-2 transition hover:border-hairline-bright"
    >
      <RouterLink
        :to="`/venues/${venue.slug}`"
        class="flex flex-1 items-center gap-4 px-4 py-3 text-left"
      >
        <span
          data-testid="venue-mood-dot"
          class="h-2.5 w-2.5 shrink-0 rounded-full"
          :class="moodDotClass(venue.mood)"
          aria-hidden="true"
        />
        <div class="flex flex-col gap-0.5">
          <span class="font-serif text-lg italic text-text">
            {{ venue.name }}
          </span>
          <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">
            {{ venue.city }}
          </span>
          <span class="text-sm text-text-2">
            {{ venue.address_line }}
          </span>
        </div>
      </RouterLink>
      <FavoriteButton :venue="venue" />
    </li>
  </ul>
</template>
