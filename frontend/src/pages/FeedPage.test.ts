import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { FeedPage as FeedPageData } from '@/types/social'

vi.mock('@/api/feed', () => ({
  fetchFeed: vi.fn(),
}))
vi.mock('@/api/kudos', () => ({
  giveKudos: vi.fn(),
  removeKudos: vi.fn(),
  fetchKudos: vi.fn(),
}))
vi.mock('@/api/users', () => ({
  searchUsers: vi.fn(),
  fetchProfile: vi.fn(),
  followUser: vi.fn(),
  unfollowUser: vi.fn(),
  fetchFollowers: vi.fn(),
  fetchFollowing: vi.fn(),
}))

import FeedPage from './FeedPage.vue'
import { fetchFeed } from '@/api/feed'

const mockedFetchFeed = vi.mocked(fetchFeed)

function makePage(overrides: Partial<FeedPageData> = {}): FeedPageData {
  return {
    items: [
      {
        public_id: 'aaaa-bbbb',
        is_public: true,
        user: { id: 2, username: 'amie' },
        ended_at: '2026-06-10T01:30:00+02:00',
        stats: { venues: 3, distance_m: 1840, duration_min: 154, moods: ['festif'] },
        narrative_snippet: 'Une nuit qui compte.',
        kudos_count: 2,
        has_kudoed: false,
      },
    ],
    nextCursor: null,
    ...overrides,
  }
}

async function mountPage() {
  const wrapper = mount(FeedPage, {
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  mockedFetchFeed.mockReset()
})

describe('FeedPage', () => {
  it('renders the feed cards', async () => {
    mockedFetchFeed.mockResolvedValue(makePage())
    const wrapper = await mountPage()

    const cards = wrapper.findAll('[data-testid="feed-card"]')
    expect(cards).toHaveLength(1)
    expect(cards[0]!.text()).toContain('amie')
    expect(cards[0]!.text()).toContain('Une nuit qui compte.')
    expect(cards[0]!.find('[data-testid="kudos-count"]').text()).toBe('2')
  })

  it('shows the empty state when nobody is followed yet', async () => {
    mockedFetchFeed.mockResolvedValue(makePage({ items: [] }))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="feed-empty"]').exists()).toBe(true)
  })

  it('offers « Voir plus » while a cursor remains', async () => {
    mockedFetchFeed.mockResolvedValue(makePage({ nextCursor: 'cursor-2' }))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="feed-more"]').exists()).toBe(true)

    await wrapper.find('[data-testid="feed-more"]').trigger('click')
    await flushPromises()

    expect(mockedFetchFeed).toHaveBeenLastCalledWith('cursor-2')
  })
})
