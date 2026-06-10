import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { Viree } from '@/types/viree'

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
      marker: vi.fn(() => chain),
      polyline: vi.fn(() => chain),
      divIcon: vi.fn(() => ({})),
      latLngBounds: vi.fn(() => ({})),
    },
  }
})

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { publicId: 'aaaa-bbbb' } }),
  useRouter: () => ({ push: vi.fn() }),
}))

vi.mock('@/api/virees', () => ({
  checkIn: vi.fn(),
  fetchCurrentViree: vi.fn(),
  closeViree: vi.fn(),
  fetchVirees: vi.fn(),
  fetchViree: vi.fn(),
}))

import VireePage from './VireePage.vue'
import { fetchViree } from '@/api/virees'

const mockedFetchViree = vi.mocked(fetchViree)

function makeViree(overrides: Partial<Viree> = {}): Viree {
  return {
    id: 1,
    public_id: 'aaaa-bbbb',
    is_public: true,
    status: 'terminee',
    started_at: '2026-06-10T21:00:00+02:00',
    ended_at: '2026-06-11T01:30:00+02:00',
    stats: { venues: 2, distance_m: 1840, duration_min: 154, moods: ['festif', 'chill'] },
    narrative: 'Du Macadam au Stereolux, une nuit qui compte.',
    weather: {
      temp: 14,
      feels_like: 12,
      condition: 'Couvert',
      icon: '04n',
      wind: 8,
      humidity: 70,
    },
    checkins: [
      {
        id: 1,
        happened_at: '2026-06-10T21:00:00+02:00',
        venue: {
          id: 1,
          name: 'Le Macadam',
          slug: 'le-macadam',
          mood: 'festif',
          latitude: 47.2,
          longitude: -1.56,
        },
      },
      {
        id: 2,
        happened_at: '2026-06-10T23:10:00+02:00',
        venue: {
          id: 2,
          name: 'Stereolux',
          slug: 'stereolux',
          mood: 'chill',
          latitude: 47.21,
          longitude: -1.57,
        },
      },
    ],
    ...overrides,
  }
}

async function mountPage() {
  const wrapper = mount(VireePage, {
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
  mockedFetchViree.mockReset()
})

describe('VireePage', () => {
  it('renders the recap: stats, narrative, weather and timeline', async () => {
    mockedFetchViree.mockResolvedValue(makeViree())
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="viree-title"]').exists()).toBe(true)
    const stats = wrapper.find('[data-testid="viree-stats"]').text()
    expect(stats).toContain('2')
    expect(stats).toContain('1,8 km')
    expect(stats).toContain('2 h 34')
    expect(wrapper.find('[data-testid="viree-narrative"]').text()).toContain(
      'une nuit qui compte',
    )
    expect(wrapper.text()).toContain('Couvert')
    expect(wrapper.findAll('[data-testid="viree-checkin"]')).toHaveLength(2)
    expect(wrapper.text()).toContain('Le Macadam')
    expect(wrapper.text()).toContain('Stereolux')
  })

  it('shows the error state when the viree cannot be loaded', async () => {
    mockedFetchViree.mockRejectedValue(new Error('404'))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="viree-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="viree-title"]').exists()).toBe(false)
  })

  it('hides the close button on someone else’s recap', async () => {
    mockedFetchViree.mockResolvedValue(makeViree({ status: 'en_cours' }))
    const wrapper = await mountPage()

    // La virée affichée est en cours mais n'est pas dans mon store local.
    expect(wrapper.find('[data-testid="viree-close"]').exists()).toBe(false)
  })
})
