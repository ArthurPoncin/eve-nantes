import type { Challenge } from '@/types/challenge'
import { apiClient } from './client'

export async function fetchChallenges(): Promise<Challenge[]> {
  const response = await apiClient.get<Challenge[]>('/api/v1/challenges')
  return response.data
}
