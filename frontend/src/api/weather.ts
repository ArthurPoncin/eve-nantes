import type { Weather } from '@/types/weather'
import { apiClient } from './client'

export async function getWeather(): Promise<Weather> {
  const response = await apiClient.get<Weather>('/api/v1/weather')
  return response.data
}
