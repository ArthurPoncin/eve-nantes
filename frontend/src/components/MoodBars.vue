<script setup lang="ts">
import { computed } from 'vue'
import type { MoodCount } from '@/types/stats'

const props = defineProps<{ moods: MoodCount[] }>()

// Tailwind v4 JIT ne détecte que les classes littérales (cf. fiche lieu).
const MOOD_BAR: Record<string, string> = {
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
const ALL_MOODS = ['festif', 'chill', 'decouverte', 'afterwork']

// Les 4 ambiances, complétées à zéro, les plus vécues en premier.
const rows = computed(() =>
  ALL_MOODS.map((mood) => ({
    mood,
    count: props.moods.find((item) => item.mood === mood)?.count ?? 0,
  })).sort((a, b) => b.count - a.count),
)

const max = computed(() => Math.max(1, ...rows.value.map((row) => row.count)))

const dominant = computed(() =>
  rows.value[0] && rows.value[0].count > 0 ? rows.value[0].mood : null,
)
</script>

<template>
  <section
    data-testid="mood-bars"
    class="glass flex flex-col gap-3 rounded-2xl border border-hairline bg-glass px-5 py-4"
  >
    <div class="flex items-baseline justify-between">
      <h2 class="font-mono text-[10px] uppercase tracking-[0.3em] text-text-3">
        Ambiances
      </h2>
      <span
        v-if="dominant"
        data-testid="mood-dominant"
        class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-2"
      >
        Ton ambiance : {{ MOOD_LABEL[dominant] }}
      </span>
    </div>

    <ul class="flex flex-col gap-2.5">
      <li
        v-for="row in rows"
        :key="row.mood"
        data-testid="mood-bar"
        class="flex items-center gap-3"
      >
        <span
          class="w-24 shrink-0 font-mono text-[10px] uppercase tracking-[0.16em] text-text-2"
        >
          {{ MOOD_LABEL[row.mood] }}
        </span>
        <span class="h-2 flex-1 overflow-hidden rounded-full bg-white/5">
          <span
            class="block h-full rounded-full transition-all"
            :class="MOOD_BAR[row.mood]"
            :style="{ width: `${(row.count / max) * 100}%` }"
            aria-hidden="true"
          />
        </span>
        <span class="w-8 shrink-0 text-right font-mono text-[11px] text-text-3">
          {{ row.count }}
        </span>
      </li>
    </ul>
  </section>
</template>
