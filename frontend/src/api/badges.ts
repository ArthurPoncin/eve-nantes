import type { Badge } from '@/types/badge'
import { apiClient } from './client'

export async function fetchBadges(): Promise<Badge[]> {
  const response = await apiClient.get<Badge[]>('/api/v1/badges')
  return response.data
}
