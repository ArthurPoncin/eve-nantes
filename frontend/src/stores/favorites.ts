import { ref } from 'vue'
import { defineStore } from 'pinia'
import { addFavorite, fetchFavorites, removeFavorite } from '@/api/favorites'
import type { Venue } from '@/types/venue'

export const useFavoritesStore = defineStore('favorites', () => {
  const slugs = ref<Set<string>>(new Set())
  const venues = ref<Venue[]>([])
  const loaded = ref(false)

  function isFavorite(slug: string): boolean {
    return slugs.value.has(slug)
  }

  async function load(): Promise<void> {
    const data = await fetchFavorites()
    venues.value = data
    slugs.value = new Set(data.map((venue) => venue.slug))
    loaded.value = true
  }

  async function toggle(venue: Venue): Promise<void> {
    if (isFavorite(venue.slug)) {
      await removeFavorite(venue.slug)
      slugs.value.delete(venue.slug)
      venues.value = venues.value.filter((item) => item.slug !== venue.slug)
    } else {
      await addFavorite(venue.slug)
      slugs.value.add(venue.slug)
      venues.value.push(venue)
    }
  }

  return { slugs, venues, loaded, isFavorite, load, toggle }
})
