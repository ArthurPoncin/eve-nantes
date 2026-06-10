import type { VenueTransport } from '@/types/transport'
import { apiClient } from './client'

export async function fetchVenueTransport(slug: string): Promise<VenueTransport> {
  const response = await apiClient.get<VenueTransport>(`/api/v1/venues/${slug}/transport`)
  return response.data
}
