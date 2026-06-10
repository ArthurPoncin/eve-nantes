import type { Review, ReviewPayload, VenueReviews } from '@/types/review'
import { apiClient } from './client'

export async function fetchVenueReviews(slug: string): Promise<VenueReviews> {
  const response = await apiClient.get<VenueReviews>(`/api/v1/venues/${slug}/reviews`)
  return response.data
}

export async function postVenueReview(
  slug: string,
  payload: ReviewPayload,
): Promise<Review> {
  const response = await apiClient.post<Review>(`/api/v1/venues/${slug}/reviews`, payload)
  return response.data
}
