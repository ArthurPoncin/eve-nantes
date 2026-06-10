<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { fetchMyStats } from '@/api/stats'
import MoodBars from '@/components/MoodBars.vue'
import PersonalHeatmap from '@/components/PersonalHeatmap.vue'
import StatCard from '@/components/StatCard.vue'
import type { MyStats } from '@/types/stats'

const stats = ref<MyStats | null>(null)
const isLoading = ref(true)
const hasError = ref(false)

const kmLabel = computed(() =>
  (stats.value?.total_km ?? 0).toLocaleString('fr-FR', { maximumFractionDigits: 1 }),
)

const streakLabel = computed(() => {
  const weeks = stats.value?.streak_weeks ?? 0
  return weeks > 0 ? `🔥 ${weeks}` : '0'
})

onMounted(async () => {
  try {
    stats.value = await fetchMyStats()
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
      data-testid="stats-loading"
      class="py-20 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Calcul de tes nuits…
    </p>
    <p
      v-else-if="hasError || stats === null"
      data-testid="stats-error"
      class="py-20 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Stats indisponibles.
    </p>

    <template v-else>
      <!-- Hero -->
      <section
        class="glass-strong relative overflow-hidden rounded-3xl border border-hairline bg-glass-strong p-6 sm:p-8"
      >
        <div
          aria-hidden="true"
          class="pointer-events-none absolute -right-16 -top-24 h-56 w-56 rounded-full bg-violet opacity-30 blur-3xl"
        />
        <p class="relative font-mono text-[11px] uppercase tracking-[0.22em] text-text-3">
          Le récap de tes nuits
        </p>
        <h1
          data-testid="stats-title"
          class="relative mt-2 font-serif text-4xl italic leading-tight text-text sm:text-5xl"
        >
          Ton Wrapped nocturne
        </h1>
      </section>

      <!-- Compteurs -->
      <section class="grid grid-cols-2 gap-3 sm:grid-cols-3" data-testid="stats-cards">
        <StatCard label="Virées" :value="String(stats.virees_count)" unit="bouclées" />
        <StatCard
          label="Check-ins"
          :value="String(stats.checkins_count)"
          unit="« J'y suis »"
        />
        <StatCard label="Lieux" :value="String(stats.distinct_venues)" unit="explorés" />
        <StatCard label="Distance" :value="kmLabel" unit="km de nuit" />
        <StatCard label="Série" :value="streakLabel" unit="semaines d'affilée" />
        <StatCard
          v-if="stats.favorite_venue"
          label="Lieu fétiche"
          :value="String(stats.favorite_venue.checkins_count)"
          :unit="`check-ins · ${stats.favorite_venue.name}`"
        />
      </section>

      <!-- Répartition des ambiances -->
      <MoodBars :moods="stats.moods" />

      <!-- Lieu fétiche, cliquable -->
      <RouterLink
        v-if="stats.favorite_venue"
        :to="`/venues/${stats.favorite_venue.slug}`"
        data-testid="stats-favorite-venue"
        class="glass flex items-center justify-between gap-3 rounded-2xl border border-gold/40 bg-glass px-5 py-4 transition hover:bg-gold/5"
      >
        <div class="flex flex-col">
          <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-gold">
            ✦ Ton QG
          </span>
          <span class="font-serif text-xl italic text-text">
            {{ stats.favorite_venue.name }}
          </span>
        </div>
        <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">
          Voir la fiche →
        </span>
      </RouterLink>

      <!-- Heatmap perso -->
      <PersonalHeatmap v-if="stats.heatmap.length > 0" :points="stats.heatmap" />
    </template>
  </main>
</template>
