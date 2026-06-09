import type { Soiree } from '@/types/soiree'
import type { Weather } from '@/types/weather'
import { apiClient } from './client'

export async function generateSoiree(mood: string, district?: string): Promise<Soiree> {
  const response = await apiClient.post<Soiree>('/api/v1/soiree/generate', {
    mood,
    ...(district ? { district } : {}),
  })
  return response.data
}

export interface ShareSoireePayload {
  email: string
  mood: string
  venue_id: number
  event_id: number | null
  narrative: string
  weather?: Weather
}

export async function shareSoiree(payload: ShareSoireePayload): Promise<void> {
  await apiClient.post('/api/v1/soiree/share', payload)
}
