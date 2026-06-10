import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { checkIn as checkInRequest, closeViree, fetchCurrentViree } from '@/api/virees'
import type { Viree } from '@/types/viree'

/** La virée en cours de l'utilisateur (le « Strava de la nuit »). */
export const useVireeStore = defineStore('viree', () => {
  const current = ref<Viree | null>(null)
  const loaded = ref(false)

  const isActive = computed(() => current.value !== null)
  const venuesCount = computed(() => current.value?.stats.venues ?? 0)
  /** Dernier lieu pointé : pilote l'état « Tu es ici » du bouton check-in. */
  const lastVenueSlug = computed(() => {
    const checkins = current.value?.checkins ?? []
    return checkins.length > 0 ? checkins[checkins.length - 1]!.venue.slug : null
  })

  async function load(): Promise<void> {
    current.value = await fetchCurrentViree()
    loaded.value = true
  }

  async function checkIn(slug: string): Promise<void> {
    current.value = await checkInRequest(slug)
  }

  /** Clôture la virée et retourne le récap (pour naviguer vers sa page). */
  async function close(): Promise<Viree> {
    const recap = await closeViree()
    current.value = null
    return recap
  }

  function reset(): void {
    current.value = null
    loaded.value = false
  }

  return {
    current,
    loaded,
    isActive,
    venuesCount,
    lastVenueSlug,
    load,
    checkIn,
    close,
    reset,
  }
})
