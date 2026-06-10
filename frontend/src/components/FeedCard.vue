<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import KudosButton from './KudosButton.vue'
import type { FeedItem } from '@/types/social'

const props = defineProps<{ item: FeedItem }>()
const emit = defineEmits<{ (e: 'kudos'): void }>()

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

const dateLabel = computed(() =>
  new Date(props.item.ended_at).toLocaleDateString('fr-FR', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  }),
)

const statsLabel = computed(() => {
  const { venues, distance_m, duration_min } = props.item.stats
  const parts = [`${venues} ${venues > 1 ? 'lieux' : 'lieu'}`]
  if (distance_m !== null) {
    parts.push(
      `${(distance_m / 1000).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} km`,
    )
  }
  const hours = Math.floor(duration_min / 60)
  parts.push(
    hours > 0
      ? `${hours} h ${String(duration_min % 60).padStart(2, '0')}`
      : `${duration_min} min`,
  )
  return parts.join(' · ')
})
</script>

<template>
  <article
    data-testid="feed-card"
    class="glass flex flex-col gap-3 rounded-2xl border border-hairline bg-glass p-5"
  >
    <header class="flex items-center justify-between gap-3">
      <RouterLink
        :to="`/u/${item.user.username}`"
        data-testid="feed-card-user"
        class="flex items-center gap-2.5"
      >
        <span
          class="flex h-9 w-9 items-center justify-center rounded-full bg-violet-bright font-serif text-base italic text-white"
          aria-hidden="true"
        >
          {{ item.user.username.charAt(0).toUpperCase() }}
        </span>
        <span class="flex flex-col">
          <span class="font-serif italic text-text">{{ item.user.username }}</span>
          <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">
            {{ dateLabel }}
          </span>
        </span>
      </RouterLink>
    </header>

    <RouterLink :to="`/viree/${item.public_id}`" class="flex flex-col gap-2.5">
      <p
        data-testid="feed-card-stats"
        class="font-mono text-[11px] uppercase tracking-[0.16em] text-text-2"
      >
        {{ statsLabel }}
      </p>
      <p
        v-if="item.narrative_snippet"
        class="font-serif italic leading-relaxed text-text"
      >
        {{ item.narrative_snippet }}
      </p>
      <span class="flex flex-wrap gap-1.5">
        <span
          v-for="mood in item.stats.moods"
          :key="mood"
          class="rounded-full border px-2.5 py-0.5 font-mono text-[10px] uppercase tracking-[0.14em]"
          :class="MOOD_CHIP[mood] ?? 'border-hairline text-text-2'"
        >
          {{ MOOD_LABEL[mood] ?? mood }}
        </span>
      </span>
    </RouterLink>

    <footer class="flex items-center justify-between">
      <KudosButton
        :count="item.kudos_count"
        :active="item.has_kudoed"
        @toggle="emit('kudos')"
      />
      <RouterLink
        :to="`/viree/${item.public_id}`"
        class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3 transition hover:text-text"
      >
        Voir le récap →
      </RouterLink>
    </footer>
  </article>
</template>
