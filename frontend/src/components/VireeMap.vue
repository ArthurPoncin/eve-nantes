<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type { Checkin } from '@/types/viree'

const props = defineProps<{ checkins: Checkin[] }>()

const mapEl = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let layer: L.LayerGroup | null = null

// Centre par défaut : Nantes (si aucun lieu géolocalisé).
const NANTES: [number, number] = [47.2184, -1.5536]

function pinClass(mood: string | null): string {
  return mood ? `noct-rpin--${mood}` : ''
}

function render(): void {
  if (!map || !layer) return
  layer.clearLayers()

  const points: [number, number][] = []
  props.checkins.forEach((checkin, index) => {
    const { latitude, longitude, mood, name } = checkin.venue
    if (latitude === null || longitude === null) return
    const latlng: [number, number] = [latitude, longitude]
    points.push(latlng)

    const icon = L.divIcon({
      className: '',
      html: `<span class="noct-rpin ${pinClass(mood)}">${index + 1}</span>`,
      iconSize: [22, 22],
      iconAnchor: [11, 11],
    })
    L.marker(latlng, { icon, keyboard: false })
      .bindTooltip(name, { direction: 'top', offset: [0, -12] })
      .addTo(layer!)
  })

  // Le tracé de la virée, étape par étape.
  if (points.length > 1) {
    L.polyline(points, {
      color: '#ff2d92',
      weight: 3,
      opacity: 0.8,
      dashArray: '6 8',
    }).addTo(layer)
  }

  if (points.length > 1) {
    map.fitBounds(L.latLngBounds(points), { padding: [48, 48], maxZoom: 16 })
  } else if (points.length === 1) {
    map.setView(points[0]!, 15)
  } else {
    map.setView(NANTES, 12)
  }
}

onMounted(() => {
  if (!mapEl.value) return
  map = L.map(mapEl.value, {
    zoomControl: false,
    scrollWheelZoom: false,
    attributionControl: true,
  }).setView(NANTES, 12)

  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap, &copy; CARTO',
    maxZoom: 19,
  }).addTo(map)

  layer = L.layerGroup().addTo(map)
  render()
  map.invalidateSize()
})

watch(() => props.checkins, render)

onBeforeUnmount(() => {
  map?.remove()
  map = null
  layer = null
})
</script>

<template>
  <div
    ref="mapEl"
    data-testid="viree-map"
    aria-label="Tracé de la virée"
    class="h-72 w-full overflow-hidden rounded-2xl border border-hairline bg-ink"
  />
</template>

<style>
/* Pins numérotés injectés par Leaflet (hors scope) — préfixe noct-rpin pour
   ne pas entrer en conflit avec ceux de VenuesMap/VenueMap. */
.noct-rpin {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 22px;
  width: 22px;
  border-radius: 9999px;
  background: var(--color-text-3, #6f6788);
  border: 1.5px solid rgba(255, 255, 255, 0.65);
  box-shadow: 0 0 10px 1px rgba(245, 241, 255, 0.35);
  color: #fff;
  font-family: var(--font-mono, monospace);
  font-size: 11px;
  font-weight: 700;
}
.noct-rpin--festif {
  background: var(--color-mood-festif, #ff2d92);
  box-shadow: 0 0 12px 2px rgba(255, 45, 146, 0.8);
}
.noct-rpin--chill {
  background: var(--color-mood-chill, #5eead4);
  color: #07060b;
  box-shadow: 0 0 12px 2px rgba(94, 234, 212, 0.7);
}
.noct-rpin--decouverte {
  background: var(--color-mood-decouverte, #a855f7);
  box-shadow: 0 0 12px 2px rgba(168, 85, 247, 0.8);
}
.noct-rpin--afterwork {
  background: var(--color-mood-afterwork, #f5c56b);
  color: #07060b;
  box-shadow: 0 0 12px 2px rgba(245, 197, 107, 0.7);
}
</style>
