<script setup lang="ts">
import WeatherWidget from '@/components/WeatherWidget.vue'

const moods = [
  { id: 'festif', label: 'Festif', dotClass: 'bg-mood-festif' },
  { id: 'chill', label: 'Chill', dotClass: 'bg-mood-chill' },
  { id: 'decouverte', label: 'Découverte', dotClass: 'bg-mood-decouverte' },
  { id: 'afterwork', label: 'Afterwork', dotClass: 'bg-mood-afterwork' },
] as const
</script>

<template>
  <main
    class="flex min-h-[80vh] flex-col items-center justify-center gap-8 px-6 text-center"
  >
    <p class="font-mono text-xs uppercase tracking-[0.3em] text-ink-muted">
      La nuit nantaise
    </p>
    <h1 class="font-serif text-5xl italic leading-tight text-ink-primary md:text-7xl">
      Recommandée pour toi
    </h1>
    <p class="max-w-md text-base text-ink-muted">
      Choisis ton humeur, on s'occupe du reste.
    </p>
    <ul class="flex flex-wrap items-center justify-center gap-3">
      <li
        v-for="mood in moods"
        :key="mood.id"
        class="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 font-mono text-[10px] uppercase tracking-widest text-ink-muted"
      >
        <span class="h-2 w-2 rounded-full" :class="mood.dotClass" />
        {{ mood.label }}
      </li>
    </ul>
    <WeatherWidget class="w-full max-w-xs" />
  </main>
</template>
