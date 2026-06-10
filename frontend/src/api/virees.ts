import type { Viree } from '@/types/viree'
import { apiClient } from './client'

export async function checkIn(slug: string): Promise<Viree> {
  const response = await apiClient.post<{ data: Viree }>(`/api/v1/venues/${slug}/checkin`)
  return response.data.data
}

export async function fetchCurrentViree(): Promise<Viree | null> {
  const response = await apiClient.get<{ data: Viree | null }>('/api/v1/virees/current')
  return response.data.data
}

export async function closeViree(): Promise<Viree> {
  const response = await apiClient.post<{ data: Viree }>('/api/v1/virees/current/close')
  return response.data.data
}

export async function fetchVirees(): Promise<Viree[]> {
  const response = await apiClient.get<{ data: Viree[] }>('/api/v1/virees')
  return response.data.data
}

export async function fetchViree(publicId: string): Promise<Viree> {
  const response = await apiClient.get<{ data: Viree }>(`/api/v1/virees/${publicId}`)
  return response.data.data
}
