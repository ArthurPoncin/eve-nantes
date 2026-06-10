import type { Weather } from './weather'

/** Lieu allégé porté par un check-in (assez pour la carte et la timeline). */
export interface CheckinVenue {
  id: number
  name: string
  slug: string
  mood: string | null
  latitude: number | null
  longitude: number | null
}

export interface Checkin {
  id: number
  happened_at: string
  venue: CheckinVenue
}

export interface VireeStats {
  venues: number
  distance_m: number | null
  duration_min: number
  moods: string[]
}

/** Virée nocturne : session de check-ins, façon activité Strava. */
export interface Viree {
  id: number
  public_id: string
  is_public: boolean
  status: 'en_cours' | 'terminee'
  started_at: string
  ended_at: string | null
  stats: VireeStats
  narrative: string | null
  weather: Weather | null
  checkins: Checkin[]
}
