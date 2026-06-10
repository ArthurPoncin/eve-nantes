import type { FeedItem, FeedPage } from '@/types/social'
import { apiClient } from './client'

export async function fetchFeed(cursor?: string): Promise<FeedPage> {
  const response = await apiClient.get<{
    data: FeedItem[]
    meta: { next_cursor: string | null }
  }>('/api/v1/feed', { params: cursor ? { cursor } : {} })

  return {
    items: response.data.data,
    nextCursor: response.data.meta.next_cursor,
  }
}
