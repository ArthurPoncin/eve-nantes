import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { EventSummary } from '@/types/event'
import type { VenueDetail } from '@/types/venue'

// Leaflet a besoin d'un vrai DOM dimensionné : on le stube pour jsdom.
vi.mock('leaflet/dist/leaflet.css', () => ({}))
vi.mock('leaflet', () => {
  const chain: Record<string, unknown> = {}
  for (const method of ['setView', 'addTo', 'remove', 'bindTooltip']) {
    chain[method] = vi.fn(() => chain)
  }
  return {
    default: {
      map: vi.fn(() => chain),
      tileLayer: vi.fn(() => chain),
      marker: vi.fn(() => chain),
      divIcon: vi.fn(() => ({})),
    },
  }
})

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { slug: 'le-macadam' } }),
  useRouter: () => ({ back: vi.fn(), push: vi.fn() }),
}))

vi.mock('@/api/venues', () => ({ fetchVenue: vi.fn() }))
vi.mock('@/api/weather', () => ({
  getWeather: vi.fn().mockRejectedValue(new Error('no key')),
}))
vi.mock('@/api/favorites', () => ({
  fetchFavorites: vi.fn().mockResolvedValue([]),
  addFavorite: vi.fn(),
  removeFavorite: vi.fn(),
}))

import VenueDetailPage from './VenueDetailPage.vue'
import { fetchVenue } from '@/api/venues'

const mockedFetchVenue = vi.mocked(fetchVenue)

function makeEvent(overrides: Partial<EventSummary> = {}): EventSummary {
  return {
    id: 1,
    title: 'Nuit Techno',
    slug: 'nuit-techno',
    description: 'Set techno mélodique toute la nuit.',
    starts_at: '2026-06-12T22:00:00Z',
    ends_at: '2026-06-13T05:00:00Z',
    price_cents: 1200,
    ...overrides,
  }
}

function makeVenue(overrides: Partial<VenueDetail> = {}): VenueDetail {
  return {
    id: 1,
    name: 'Le Macadam',
    slug: 'le-macadam',
    address_line: '10 Bd Léon Bureau',
    postal_code: '44200',
    city: 'Nantes',
    mood: 'festif',
    capacity: 400,
    latitude: 47.2,
    longitude: -1.56,
    events: [],
    ...overrides,
  }
}

async function mountPage() {
  const wrapper = mount(VenueDetailPage, {
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
  mockedFetchVenue.mockReset()
})

describe('VenueDetailPage', () => {
  it('renders the venue name and full address once loaded', async () => {
    mockedFetchVenue.mockResolvedValue(makeVenue())
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="venue-detail-name"]').text()).toBe('Le Macadam')
    expect(wrapper.text()).toContain('10 Bd Léon Bureau')
    expect(wrapper.text()).toContain('44200 Nantes')
  })

  it('shows the error state when the venue cannot be loaded', async () => {
    mockedFetchVenue.mockRejectedValue(new Error('404'))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="venue-detail-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="venue-detail-name"]').exists()).toBe(false)
  })

  it('renders one card per event', async () => {
    mockedFetchVenue.mockResolvedValue(
      makeVenue({ events: [makeEvent({ id: 1 }), makeEvent({ id: 2, title: 'Afterwork Jazz' })] }),
    )
    const wrapper = await mountPage()

    expect(wrapper.findAll('[data-testid="venue-event"]')).toHaveLength(2)
    expect(wrapper.text()).toContain('Afterwork Jazz')
  })

  it('shows the empty state when there are no events', async () => {
    mockedFetchVenue.mockResolvedValue(makeVenue({ events: [] }))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="venue-events-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="venue-event"]').exists()).toBe(false)
  })

  it('renders the map when coordinates are present', async () => {
    mockedFetchVenue.mockResolvedValue(makeVenue({ latitude: 47.2, longitude: -1.56 }))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="venue-map"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="venue-itinerary-link"]').exists()).toBe(true)
  })

  it('falls back gracefully when coordinates are missing', async () => {
    mockedFetchVenue.mockResolvedValue(makeVenue({ latitude: null, longitude: null }))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="venue-map"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="venue-map-fallback"]').exists()).toBe(true)
  })

  it('offers a login CTA when the visitor is anonymous', async () => {
    mockedFetchVenue.mockResolvedValue(makeVenue())
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="venue-cta-login"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="venue-cta-favorite"]').exists()).toBe(false)
  })

  it('offers the favorite CTA when the visitor is authenticated', async () => {
    localStorage.setItem('noctambule.token', 'tok_abc')
    setActivePinia(createPinia())
    mockedFetchVenue.mockResolvedValue(makeVenue())

    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="venue-cta-favorite"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="venue-cta-login"]').exists()).toBe(false)
  })
})
