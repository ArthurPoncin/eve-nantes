import { apiClient } from './client'
import type { AuthResponse, AuthUser, Credentials, RegisterPayload } from '@/types/auth'

export async function registerRequest(payload: RegisterPayload): Promise<AuthResponse> {
  const { data } = await apiClient.post<AuthResponse>('/api/v1/auth/register', payload)
  return data
}

export async function loginRequest(credentials: Credentials): Promise<AuthResponse> {
  const { data } = await apiClient.post<AuthResponse>('/api/v1/auth/login', credentials)
  return data
}

export async function logoutRequest(): Promise<void> {
  await apiClient.post('/api/v1/auth/logout')
}

export async function fetchMe(): Promise<AuthUser> {
  const { data } = await apiClient.get<AuthUser>('/api/v1/auth/me')
  return data
}
