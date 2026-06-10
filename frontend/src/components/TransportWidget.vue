<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchVenueTransport } from '@/api/transport'
import type { VenueTransport } from '@/types/transport'

const props = defineProps<{ slug: string }>()

const transport = ref<VenueTransport | null>(null)
const isLoading = ref(true)

// Badge de ligne teinté selon le type TAN (tram / busway / bus / navibus).
const TYPE_BADGE: Record<string, string> = {
  tram: 'border-cyan/40 text-cyan',
  busway: 'border-violet-bright/40 text-violet-bright',
  bus: 'border-gold/40 text-gold',
  navibus: 'border-pink/40 text-pink',
}
const TYPE_LABEL: Record<string, string> = {
  tram: 'Tram',
  busway: 'Busway',
  bus: 'Bus',
  navibus: 'Navibus',
}

function badgeClass(type: string): string {
  return TYPE_BADGE[type] ?? 'border-hairline text-text-2'
}
function typeLabel(type: string): string {
  return TYPE_LABEL[type] ?? 'Bus'
}

onMounted(async () => {
  try {
    transport.value = await fetchVenueTransport(props.slug)
  } catch {
    // API TAN indisponible : on masque simplement le bloc.
    transport.value = null
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div
    v-if="isLoading"
    data-testid="transport-skeleton"
    class="glass flex flex-col gap-3 rounded-2xl border border-hairline bg-glass px-5 py-4"
    aria-hidden="true"
  >
    <div class="h-4 w-40 animate-pulse rounded bg-white/10" />
    <div class="h-3 w-full animate-pulse rounded bg-white/10" />
    <div class="h-3 w-2/3 animate-pulse rounded bg-white/10" />
  </div>

  <section
    v-else-if="transport?.stop"
    aria-label="Prochains passages TAN"
    data-testid="transport-widget"
    class="glass flex flex-col gap-3 rounded-2xl border border-hairline bg-glass px-5 py-4"
  >
    <div class="flex items-baseline justify-between gap-3">
      <p
        data-testid="transport-stop-name"
        class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3"
      >
        🚊 Arrêt {{ transport.stop.name }}
      </p>
      <span
        v-if="transport.stop.distance"
        class="shrink-0 font-mono text-[10px] uppercase tracking-[0.16em] text-text-3"
      >
        à {{ transport.stop.distance }}
      </span>
    </div>

    <p
      v-if="transport.departures.length === 0"
      data-testid="transport-empty"
      class="font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Aucun passage imminent.
    </p>

    <ul v-else class="flex flex-col gap-2">
      <li
        v-for="(departure, index) in transport.departures"
        :key="index"
        data-testid="transport-departure"
        class="flex items-center gap-3"
      >
        <span
          class="flex h-7 min-w-7 shrink-0 items-center justify-center rounded-lg border px-1.5 font-mono text-xs font-bold"
          :class="badgeClass(departure.type)"
          :aria-label="`${typeLabel(departure.type)} ligne ${departure.line}`"
        >
          {{ departure.line }}
        </span>
        <span class="flex-1 truncate text-sm text-text-2"
          >→ {{ departure.terminus }}</span
        >
        <span class="flex shrink-0 items-center gap-1.5 font-mono text-xs text-text">
          <span
            v-if="departure.realtime"
            data-testid="transport-realtime"
            class="h-1.5 w-1.5 animate-pulse rounded-full bg-mood-chill"
            :title="'Temps réel'"
          >
            <span class="sr-only">temps réel</span>
          </span>
          {{ departure.wait }}
        </span>
      </li>
    </ul>
  </section>
</template>
