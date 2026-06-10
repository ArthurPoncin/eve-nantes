import type { Viree, VireeStats } from './viree'

/** Identité publique d'un noctambule — jamais d'email ici. */
export interface UserSummary {
  id: number
  username: string
}

export interface SearchResult extends UserSummary {
  followers_count: number
  is_following: boolean
}

export interface PublicProfile {
  username: string
  member_since: string
  badge_count: number
  followers_count: number
  following_count: number
  /** null pour un visiteur anonyme. */
  is_following: boolean | null
  stats: {
    virees_count: number
    total_km: number
    distinct_venues: number
  }
  recent_virees: Viree[]
}

/** Une virée bouclée dans le fil. */
export interface FeedItem {
  public_id: string
  is_public: boolean
  user: UserSummary
  ended_at: string
  stats: VireeStats
  narrative_snippet: string | null
  kudos_count: number
  has_kudoed: boolean
}

export interface FeedPage {
  items: FeedItem[]
  nextCursor: string | null
}
