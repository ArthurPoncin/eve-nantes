import type { Soiree } from '@/types/soiree'
import { apiClient } from './client'

export async function generateSoiree(mood: string, district?: string): Promise<Soiree> {
  const response = await apiClient.post<Soiree>('/api/v1/soiree/generate', {
    mood,
    ...(district ? { district } : {}),
  })
  return response.data
}
