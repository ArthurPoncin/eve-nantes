<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps<{
  latitude: number | null
  longitude: number | null
  name: string
  address: string
}>()

const mapEl = ref<HTMLElement | null>(null)
let map: L.Map | null = null

const hasCoords = computed(
  () => props.latitude !== null && props.longitude !== null,
)

// Lien d'itinéraire OpenStreetMap (sans clé d'API) vers la destination.
const directionsUrl = computed(() => {
  if (props.latitude === null || props.longitude === null) return null
  return `https://www.openstreetmap.org/directions?to=${props.latitude}%2C${props.longitude}`
})

onMounted(() => {
  if (!mapEl.value || props.latitude === null || props.longitude === null) return

  map = L.map(mapEl.value, {
    zoomControl: false,
    scrollWheelZoom: false,
    attributionControl: true,
  }).setView([props.latitude, props.longitude], 15)

  // Tuiles sombres CARTO (OpenStreetMap) — gratuit, sans clé, raccord avec le thème.
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap, &copy; CARTO',
    maxZoom: 19,
  }).addTo(map)

  // Épingle néon en HTML (divIcon) : évite les assets d'icône cassés par le bundler
  // et reste raccord avec le design (halo rose pulsé).
  const icon = L.divIcon({
    className: '',
    html: '<span class="noct-pin"><span class="noct-pin__ring"></span></span>',
    iconSize: [14, 14],
    iconAnchor: [7, 7],
  })
  L.marker([props.latitude, props.longitude], { icon, keyboard: false })
    .addTo(map)
    .bindTooltip(props.name, { direction: 'top', offset: [0, -8] })
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
})
</script>

<template>
  <section
    aria-label="Localisation du lieu"
    class="glass overflow-hidden rounded-2xl border border-hairline bg-glass"
  >
    <div class="flex items-center justify-between gap-3 px-5 pt-4">
      <div class="flex min-w-0 flex-col">
        <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
          Y aller
        </span>
        <span class="truncate text-sm text-text-2">{{ address }}</span>
      </div>
      <a
        v-if="directionsUrl"
        :href="directionsUrl"
        target="_blank"
        rel="noopener noreferrer"
        data-testid="venue-itinerary-link"
        class="glow-cyan shrink-0 rounded-full border border-cyan/40 px-3 py-1.5 font-mono text-[10px] uppercase tracking-[0.16em] text-cyan transition hover:bg-cyan/10"
      >
        Itinéraire ↗
      </a>
    </div>

    <div
      v-if="hasCoords"
      ref="mapEl"
      data-testid="venue-map"
      aria-hidden="true"
      class="mt-4 h-56 w-full bg-ink"
    />
    <p
      v-else
      data-testid="venue-map-fallback"
      class="mt-4 flex h-24 items-center justify-center px-5 pb-4 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Position non disponible
    </p>
  </section>
</template>

<style>
/* Épingle injectée par Leaflet hors du scope du composant → styles non-scopés. */
.noct-pin {
  position: relative;
  display: block;
  height: 14px;
  width: 14px;
  border-radius: 9999px;
  background: var(--color-pink, #ff2d92);
  box-shadow: 0 0 12px 2px rgba(255, 45, 146, 0.85);
}
.noct-pin__ring {
  position: absolute;
  inset: -6px;
  border-radius: 9999px;
  border: 1px solid rgba(255, 45, 146, 0.6);
  animation: noct-pulse 2s ease-out infinite;
}
@keyframes noct-pulse {
  0% {
    transform: scale(0.6);
    opacity: 0.9;
  }
  100% {
    transform: scale(1.7);
    opacity: 0;
  }
}

/* Le conteneur reste sombre pendant le chargement des tuiles + attribution discrète. */
.leaflet-container {
  background: #07060b;
  font-family: inherit;
}
.leaflet-tooltip {
  background: rgba(26, 22, 40, 0.92);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #f5f1ff;
  font-family: var(--font-mono, monospace);
  font-size: 11px;
}
.leaflet-tooltip-top::before {
  border-top-color: rgba(26, 22, 40, 0.92);
}
.leaflet-control-attribution {
  background: rgba(7, 6, 11, 0.7) !important;
  color: #6f6788 !important;
}
.leaflet-control-attribution a {
  color: #8a7fb0 !important;
}
</style>
