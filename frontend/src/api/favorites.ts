import type { Venue } from '@/types/venue'
import { apiClient } from './client'

export async function fetchFavorites(): Promise<Venue[]> {
  const response = await apiClient.get<{ data: Venue[] }>('/api/v1/favorites')
  return response.data.data
}

export async function addFavorite(slug: string): Promise<void> {
  await apiClient.post(`/api/v1/venues/${slug}/favorite`)
}

export async function removeFavorite(slug: string): Promise<void> {
  await apiClient.delete(`/api/v1/venues/${slug}/favorite`)
}
