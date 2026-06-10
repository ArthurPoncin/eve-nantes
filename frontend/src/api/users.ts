import type { PublicProfile, SearchResult, UserSummary } from '@/types/social'
import { apiClient } from './client'

export async function searchUsers(q: string): Promise<SearchResult[]> {
  const response = await apiClient.get<{ data: SearchResult[] }>('/api/v1/users/search', {
    params: { q },
  })
  return response.data.data
}

export async function fetchProfile(username: string): Promise<PublicProfile> {
  const response = await apiClient.get<PublicProfile>(`/api/v1/users/${username}`)
  return response.data
}

export async function followUser(username: string): Promise<{ followers_count: number }> {
  const response = await apiClient.post<{ followers_count: number }>(
    `/api/v1/users/${username}/follow`,
  )
  return response.data
}

export async function unfollowUser(username: string): Promise<void> {
  await apiClient.delete(`/api/v1/users/${username}/follow`)
}

export async function fetchFollowers(username: string): Promise<UserSummary[]> {
  const response = await apiClient.get<{ data: UserSummary[] }>(
    `/api/v1/users/${username}/followers`,
  )
  return response.data.data
}

export async function fetchFollowing(username: string): Promise<UserSummary[]> {
  const response = await apiClient.get<{ data: UserSummary[] }>(
    `/api/v1/users/${username}/following`,
  )
  return response.data.data
}
