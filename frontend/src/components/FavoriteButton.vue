<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'
import type { Venue } from '@/types/venue'

const props = defineProps<{ venue: Venue }>()

const auth = useAuthStore()
const favorites = useFavoritesStore()

const isFavorite = computed(() => favorites.isFavorite(props.venue.slug))

function onToggle(): void {
  favorites.toggle(props.venue).catch(() => {})
}
</script>

<template>
  <button
    v-if="auth.isAuthenticated"
    type="button"
    data-testid="favorite-button"
    :aria-pressed="isFavorite"
    :aria-label="isFavorite ? 'Retirer des favoris' : 'Ajouter des favoris'"
    class="shrink-0 rounded-full p-2 transition-colors hover:bg-white/10"
    :class="isFavorite ? 'text-mood-festif' : 'text-ink-muted'"
    @click.stop.prevent="onToggle"
  >
    <svg
      class="h-5 w-5"
      viewBox="0 0 24 24"
      :fill="isFavorite ? 'currentColor' : 'none'"
      stroke="currentColor"
      stroke-width="2"
      aria-hidden="true"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M12 21s-6.716-4.35-9.193-7.5C1.07 11.3 1.3 8.2 3.6 6.6c1.9-1.32 4.4-.8 5.7 1l.7.95.7-.95c1.3-1.8 3.8-2.32 5.7-1 2.3 1.6 2.53 4.7.793 6.9C18.716 16.65 12 21 12 21z"
      />
    </svg>
  </button>
</template>
