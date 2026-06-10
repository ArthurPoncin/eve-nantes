import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { MyStats } from '@/types/stats'

// Leaflet a besoin d'un vrai DOM dimensionné : on le stube pour jsdom.
vi.mock('leaflet/dist/leaflet.css', () => ({}))
vi.mock('leaflet', () => {
  const chain: Record<string, unknown> = {}
  for (const method of [
    'setView',
    'addTo',
    'remove',
    'bindTooltip',
    'clearLayers',
    'fitBounds',
    'invalidateSize',
  ]) {
    chain[method] = vi.fn(() => chain)
  }
  return {
    default: {
      map: vi.fn(() => chain),
      tileLayer: vi.fn(() => chain),
      layerGroup: vi.fn(() => chain),
      circleMarker: vi.fn(() => chain),
      latLngBounds: vi.fn(() => ({})),
    },
  }
})

vi.mock('@/api/stats', () => ({
  fetchMyStats: vi.fn(),
  fetchPilier: vi.fn(),
}))

import ProfileStatsPage from './ProfileStatsPage.vue'
import { fetchMyStats } from '@/api/stats'

const mockedFetchMyStats = vi.mocked(fetchMyStats)

function makeStats(overrides: Partial<MyStats> = {}): MyStats {
  return {
    virees_count: 12,
    checkins_count: 47,
    distinct_venues: 9,
    total_km: 23.4,
    streak_weeks: 3,
    dominant_mood: 'festif',
    moods: [
      { mood: 'festif', count: 18 },
      { mood: 'chill', count: 12 },
    ],
    favorite_venue: { slug: 'le-chat-noir', name: 'Le Chat Noir', checkins_count: 9 },
    heatmap: [
      {
        slug: 'le-chat-noir',
        name: 'Le Chat Noir',
        latitude: 47.214,
        longitude: -1.553,
        checkins_count: 9,
      },
    ],
    ...overrides,
  }
}

async function mountPage() {
  const wrapper = mount(ProfileStatsPage, {
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  mockedFetchMyStats.mockReset()
})

describe('ProfileStatsPage', () => {
  it('renders the wrapped: counters, moods, favorite venue and heatmap', async () => {
    mockedFetchMyStats.mockResolvedValue(makeStats())
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="stats-title"]').text()).toContain(
      'Wrapped nocturne',
    )
    const cards = wrapper.find('[data-testid="stats-cards"]').text()
    expect(cards).toContain('12')
    expect(cards).toContain('47')
    expect(cards).toContain('23,4')
    expect(cards).toContain('🔥 3')
    expect(wrapper.find('[data-testid="mood-dominant"]').text()).toContain('Festif')
    expect(wrapper.find('[data-testid="stats-favorite-venue"]').text()).toContain(
      'Le Chat Noir',
    )
    expect(wrapper.find('[data-testid="personal-heatmap"]').exists()).toBe(true)
  })

  it('hides the favorite venue and heatmap for a fresh user', async () => {
    mockedFetchMyStats.mockResolvedValue(
      makeStats({
        virees_count: 0,
        checkins_count: 0,
        moods: [],
        favorite_venue: null,
        heatmap: [],
      }),
    )
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="stats-favorite-venue"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="personal-heatmap"]').exists()).toBe(false)
  })

  it('shows the error state when stats cannot be loaded', async () => {
    mockedFetchMyStats.mockRejectedValue(new Error('500'))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="stats-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="stats-title"]').exists()).toBe(false)
  })
})
