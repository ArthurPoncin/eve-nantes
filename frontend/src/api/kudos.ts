import type { UserSummary } from '@/types/social'
import { apiClient } from './client'

export async function giveKudos(publicId: string): Promise<{ kudos_count: number }> {
  const response = await apiClient.post<{ kudos_count: number }>(
    `/api/v1/virees/${publicId}/kudos`,
  )
  return response.data
}

export async function removeKudos(publicId: string): Promise<void> {
  await apiClient.delete(`/api/v1/virees/${publicId}/kudos`)
}

export async function fetchKudos(
  publicId: string,
): Promise<{ count: number; users: UserSummary[] }> {
  const response = await apiClient.get<{ count: number; users: UserSummary[] }>(
    `/api/v1/virees/${publicId}/kudos`,
  )
  return response.data
}
