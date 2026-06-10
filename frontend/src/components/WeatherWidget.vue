<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { getWeather } from '@/api/weather'
import type { Weather } from '@/types/weather'

const weather = ref<Weather | null>(null)
const isLoading = ref(true)
const hasError = ref(false)

onMounted(async () => {
  try {
    weather.value = await getWeather()
  } catch {
    hasError.value = true
  } finally {
    isLoading.value = false
  }
})

function iconUrl(icon: string): string {
  return `https://openweathermap.org/img/wn/${icon}@2x.png`
}
</script>

<template>
  <section
    aria-label="Météo Nantes"
    data-testid="weather-widget"
    class="glass flex items-center gap-4 rounded-2xl border border-hairline bg-glass px-5 py-4"
  >
    <template v-if="isLoading">
      <div
        data-testid="weather-skeleton"
        class="flex w-full items-center gap-4"
        aria-hidden="true"
      >
        <div class="h-12 w-12 animate-pulse rounded-full bg-white/10" />
        <div class="flex flex-1 flex-col gap-2">
          <div class="h-4 w-20 animate-pulse rounded bg-white/10" />
          <div class="h-3 w-32 animate-pulse rounded bg-white/10" />
        </div>
      </div>
      <span class="sr-only">Chargement de la météo…</span>
    </template>

    <template v-else-if="hasError">
      <span class="text-2xl" aria-hidden="true">🌙</span>
      <p
        data-testid="weather-error"
        class="font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
      >
        Météo indisponible
      </p>
    </template>

    <template v-else-if="weather">
      <img
        :src="iconUrl(weather.icon)"
        :alt="weather.condition"
        class="h-12 w-12"
        width="48"
        height="48"
      />
      <div class="flex flex-col text-left">
        <span data-testid="weather-temp" class="font-serif text-3xl italic text-text">
          {{ Math.round(weather.temp) }}°C
        </span>
        <span data-testid="weather-condition" class="text-sm capitalize text-text-2">
          {{ weather.condition }}
        </span>
      </div>
    </template>
  </section>
</template>
