export interface Review {
  id: number
  username: string
  rating: number
  comment: string | null
  created_at: string
}

export interface VenueReviews {
  /** Note moyenne sur 5, null si aucun avis. */
  average: number | null
  count: number
  reviews: Review[]
}

export interface ReviewPayload {
  rating: number
  comment?: string
}
