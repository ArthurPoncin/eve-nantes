<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type { Venue } from '@/types/venue'

const props = defineProps<{
  venues: Venue[]
  selectedSlug: string | null
}>()
const emit = defineEmits<{ (e: 'select', slug: string): void }>()

const mapEl = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let markerLayer: L.LayerGroup | null = null
const markersBySlug = new Map<string, L.Marker>()

// Centre par défaut : Nantes (utilisé si aucun lieu géolocalisé).
const NANTES: [number, number] = [47.2184, -1.5536]

function moodPinClass(mood: string | null): string {
  return mood ? `noct-mpin--${mood}` : ''
}

function applySelection(): void {
  for (const [slug, marker] of markersBySlug) {
    const pin = marker.getElement()?.querySelector('.noct-mpin')
    pin?.classList.toggle('is-selected', slug === props.selectedSlug)
  }
}

function renderMarkers(): void {
  if (!map || !markerLayer) return
  markerLayer.clearLayers()
  markersBySlug.clear()

  const points: [number, number][] = []
  for (const v of props.venues) {
    if (v.latitude === null || v.longitude === null) continue
    const latlng: [number, number] = [v.latitude, v.longitude]
    points.push(latlng)

    const icon = L.divIcon({
      className: '',
      html: `<span class="noct-mpin ${moodPinClass(v.mood)}"></span>`,
      iconSize: [16, 16],
      iconAnchor: [8, 8],
    })
    const marker = L.marker(latlng, { icon, keyboard: false })
      .bindTooltip(v.name, { direction: 'top', offset: [0, -10] })
      .on('click', () => emit('select', v.slug))
    marker.addTo(markerLayer)
    markersBySlug.set(v.slug, marker)
  }

  if (points.length > 0) {
    map.fitBounds(L.latLngBounds(points), { padding: [56, 56], maxZoom: 15 })
  } else {
    map.setView(NANTES, 12)
  }
  applySelection()
}

function focusSelected(): void {
  if (!map || !props.selectedSlug) return
  const marker = markersBySlug.get(props.selectedSlug)
  if (marker) {
    map.setView(marker.getLatLng(), Math.max(map.getZoom(), 15), { animate: true })
    marker.openTooltip()
  }
  applySelection()
}

onMounted(() => {
  if (!mapEl.value) return
  map = L.map(mapEl.value, {
    zoomControl: true,
    scrollWheelZoom: true,
    attributionControl: true,
  }).setView(NANTES, 12)

  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap, &copy; CARTO',
    maxZoom: 19,
  }).addTo(map)

  markerLayer = L.layerGroup().addTo(map)
  renderMarkers()
  // La taille du conteneur flex peut se stabiliser après le montage.
  map.invalidateSize()
})

watch(() => props.venues, renderMarkers)
watch(() => props.selectedSlug, focusSelected)

onBeforeUnmount(() => {
  map?.remove()
  map = null
  markerLayer = null
  markersBySlug.clear()
})
</script>

<template>
  <div
    ref="mapEl"
    data-testid="venues-map"
    aria-label="Carte des lieux"
    class="h-full w-full bg-ink"
  />
</template>

<style>
/* Pins injectés par Leaflet (hors scope du composant) → styles non-scopés,
   préfixés noct-mpin pour ne pas entrer en conflit avec le pin de VenueMap. */
.noct-mpin {
  display: block;
  height: 14px;
  width: 14px;
  border-radius: 9999px;
  background: var(--color-text-3, #6f6788);
  border: 1.5px solid rgba(255, 255, 255, 0.55);
  box-shadow: 0 0 10px 1px rgba(245, 241, 255, 0.35);
  transition: transform 0.15s ease;
  cursor: pointer;
}
.noct-mpin--festif {
  background: var(--color-mood-festif, #ff2d92);
  box-shadow: 0 0 12px 2px rgba(255, 45, 146, 0.8);
}
.noct-mpin--chill {
  background: var(--color-mood-chill, #5eead4);
  box-shadow: 0 0 12px 2px rgba(94, 234, 212, 0.7);
}
.noct-mpin--decouverte {
  background: var(--color-mood-decouverte, #a855f7);
  box-shadow: 0 0 12px 2px rgba(168, 85, 247, 0.8);
}
.noct-mpin--afterwork {
  background: var(--color-mood-afterwork, #f5c56b);
  box-shadow: 0 0 12px 2px rgba(245, 197, 107, 0.7);
}
.noct-mpin.is-selected {
  transform: scale(1.7);
  border-color: #fff;
}

/* Thème sombre du conteneur + infobulles (repris de VenueMap pour être autonome). */
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
.leaflet-bar a {
  background: rgba(26, 22, 40, 0.92);
  color: #f5f1ff;
  border-bottom-color: rgba(255, 255, 255, 0.12);
}
.leaflet-bar a:hover {
  background: rgba(40, 34, 60, 0.95);
}
</style>
