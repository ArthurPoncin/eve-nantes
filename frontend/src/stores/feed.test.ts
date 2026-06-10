import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'
import { createPinia, setActivePinia } from 'pinia'
import { useFeedStore } from './feed'
import type { FeedItem } from '@/types/social'

const server = setupServer()

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

function makeItem(overrides: Partial<FeedItem> = {}): FeedItem {
  return {
    public_id: 'aaaa-bbbb',
    is_public: true,
    user: { id: 2, username: 'amie' },
    ended_at: '2026-06-10T01:30:00+02:00',
    stats: { venues: 3, distance_m: 1840, duration_min: 154, moods: ['festif'] },
    narrative_snippet: 'Une nuit qui compte.',
    kudos_count: 2,
    has_kudoed: false,
    ...overrides,
  }
}

describe('feed store', () => {
  it('load() fills items and the next cursor', async () => {
    server.use(
      http.get('*/api/v1/feed', () =>
        HttpResponse.json({
          data: [makeItem()],
          meta: { next_cursor: 'cursor-2' },
        }),
      ),
    )

    const feed = useFeedStore()
    await feed.load()

    expect(feed.loaded).toBe(true)
    expect(feed.items).toHaveLength(1)
    expect(feed.nextCursor).toBe('cursor-2')
  })

  it('loadMore() appends the next page using the cursor', async () => {
    let cursorSeen: string | null = null
    server.use(
      http.get('*/api/v1/feed', ({ request }) => {
        const cursor = new URL(request.url).searchParams.get('cursor')
        if (cursor === null) {
          return HttpResponse.json({
            data: [makeItem()],
            meta: { next_cursor: 'cursor-2' },
          })
        }
        cursorSeen = cursor
        return HttpResponse.json({
          data: [makeItem({ public_id: 'cccc-dddd' })],
          meta: { next_cursor: null },
        })
      }),
    )

    const feed = useFeedStore()
    await feed.load()
    await feed.loadMore()

    expect(cursorSeen).toBe('cursor-2')
    expect(feed.items).toHaveLength(2)
    expect(feed.nextCursor).toBeNull()
  })

  it('toggleKudos() is optimistic and persists via the API', async () => {
    let posted = false
    server.use(
      http.post('*/api/v1/virees/aaaa-bbbb/kudos', () => {
        posted = true
        return HttpResponse.json({ kudos_count: 3 }, { status: 201 })
      }),
    )

    const feed = useFeedStore()
    const item = makeItem()
    feed.items = [item]

    await feed.toggleKudos(item)

    expect(posted).toBe(true)
    expect(item.has_kudoed).toBe(true)
    expect(item.kudos_count).toBe(3)
  })

  it('toggleKudos() rolls back when the API refuses', async () => {
    server.use(
      http.post('*/api/v1/virees/aaaa-bbbb/kudos', () =>
        HttpResponse.json({ message: 'non' }, { status: 422 }),
      ),
    )

    const feed = useFeedStore()
    const item = makeItem()
    feed.items = [item]

    await feed.toggleKudos(item)

    expect(item.has_kudoed).toBe(false)
    expect(item.kudos_count).toBe(2)
  })
})
