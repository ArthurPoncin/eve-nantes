import type { MyStats, Pilier } from '@/types/stats'
import { apiClient } from './client'

export async function fetchMyStats(): Promise<MyStats> {
  const response = await apiClient.get<MyStats>('/api/v1/me/stats')
  return response.data
}

export async function fetchPilier(slug: string): Promise<Pilier | null> {
  const response = await apiClient.get<{ pilier: Pilier | null }>(
    `/api/v1/venues/${slug}/pilier`,
  )
  return response.data.pilier
}
