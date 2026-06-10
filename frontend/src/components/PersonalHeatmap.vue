<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type { HeatmapPoint } from '@/types/stats'

const props = defineProps<{ points: HeatmapPoint[] }>()

const mapEl = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let layer: L.LayerGroup | null = null

// Centre par défaut : Nantes.
const NANTES: [number, number] = [47.2184, -1.5536]

function render(): void {
  if (!map || !layer) return
  layer.clearLayers()

  const max = Math.max(1, ...props.points.map((point) => point.checkins_count))
  const latlngs: [number, number][] = []

  for (const point of props.points) {
    const latlng: [number, number] = [point.latitude, point.longitude]
    latlngs.push(latlng)
    const ratio = point.checkins_count / max

    // Pas de plugin heat : des cercles roses dont la taille et l'opacité
    // suivent le nombre de check-ins suffisent à l'échelle d'une ville.
    L.circleMarker(latlng, {
      radius: 6 + 12 * ratio,
      color: '#ff2d92',
      weight: 1,
      fillColor: '#ff2d92',
      fillOpacity: 0.35 + 0.45 * ratio,
    })
      .bindTooltip(
        `${point.name} — ${point.checkins_count} check-in${point.checkins_count > 1 ? 's' : ''}`,
        { direction: 'top', offset: [0, -8] },
      )
      .addTo(layer)
  }

  if (latlngs.length > 1) {
    map.fitBounds(L.latLngBounds(latlngs), { padding: [40, 40], maxZoom: 15 })
  } else if (latlngs.length === 1) {
    map.setView(latlngs[0]!, 14)
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

watch(() => props.points, render)

onBeforeUnmount(() => {
  map?.remove()
  map = null
  layer = null
})
</script>

<template>
  <section
    aria-label="Tes lieux de prédilection"
    class="glass overflow-hidden rounded-2xl border border-hairline bg-glass"
  >
    <div class="px-5 pt-4">
      <h2 class="font-mono text-[10px] uppercase tracking-[0.3em] text-text-3">
        Ta carte des nuits
      </h2>
    </div>
    <div
      ref="mapEl"
      data-testid="personal-heatmap"
      aria-hidden="true"
      class="mt-4 h-64 w-full bg-ink"
    />
  </section>
</template>
