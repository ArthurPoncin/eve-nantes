import type { Venue, VenueDetail } from '@/types/venue'
import { apiClient } from './client'

export async function fetchVenues(mood?: string): Promise<Venue[]> {
  const response = await apiClient.get<{ data: Venue[] }>('/api/v1/venues', {
    params: mood ? { mood } : undefined,
  })
  return response.data.data
}

export async function fetchVenue(slug: string): Promise<VenueDetail> {
  const response = await apiClient.get<{ data: VenueDetail }>(`/api/v1/venues/${slug}`)
  return response.data.data
}
