export interface MoodCount {
  mood: string
  count: number
}

export interface HeatmapPoint {
  slug: string
  name: string
  latitude: number
  longitude: number
  checkins_count: number
}

export interface FavoriteVenue {
  slug: string
  name: string
  checkins_count: number
}

/** Le « Wrapped » nocturne : tous les agrégats de GET /me/stats. */
export interface MyStats {
  virees_count: number
  checkins_count: number
  distinct_venues: number
  total_km: number
  streak_weeks: number
  dominant_mood: string | null
  moods: MoodCount[]
  favorite_venue: FavoriteVenue | null
  heatmap: HeatmapPoint[]
}

/** Le « Pilier de bar » d'un lieu : top check-iner des 90 derniers jours. */
export interface Pilier {
  username: string
  checkins_count: number
  first_checkin_at: string
}
