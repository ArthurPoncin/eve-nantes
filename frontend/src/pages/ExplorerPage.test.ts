import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import type { Venue } from '@/types/venue'

// Leaflet manipule le DOM réel : on le neutralise (jsdom n'a pas de canvas/tuiles).
vi.mock('leaflet/dist/leaflet.css', () => ({}))
vi.mock('leaflet', () => {
  const marker = {
    bindTooltip: vi.fn().mockReturnThis(),
    on: vi.fn().mockReturnThis(),
    addTo: vi.fn().mockReturnThis(),
    getElement: vi.fn(() => null),
    getLatLng: vi.fn(() => ({ lat: 0, lng: 0 })),
    openTooltip: vi.fn().mockReturnThis(),
  }
  const layerGroup = { addTo: vi.fn().mockReturnThis(), clearLayers: vi.fn().mockReturnThis() }
  const map = {
    setView: vi.fn().mockReturnThis(),
    fitBounds: vi.fn().mockReturnThis(),
    getZoom: vi.fn(() => 12),
    invalidateSize: vi.fn().mockReturnThis(),
    remove: vi.fn(),
  }
  const tileLayer = { addTo: vi.fn().mockReturnThis() }
  return {
    default: {
      map: vi.fn(() => map),
      tileLayer: vi.fn(() => tileLayer),
      layerGroup: vi.fn(() => layerGroup),
      marker: vi.fn(() => marker),
      divIcon: vi.fn(() => ({})),
      latLngBounds: vi.fn(() => ({})),
    },
  }
})

const fetchVenuesMock = vi.fn()
vi.mock('@/api/venues', () => ({
  fetchVenues: (...args: unknown[]) => fetchVenuesMock(...args),
}))

import ExplorerPage from './ExplorerPage.vue'

function makeVenue(overrides: Partial<Venue> = {}): Venue {
  return {
    id: 1,
    name: 'Le Lieu Unique',
    slug: 'le-lieu-unique',
    address_line: '2 Rue de la Biscuiterie',
    postal_code: '44000',
    city: 'Nantes',
    mood: 'festif',
    capacity: 800,
    latitude: 47.2138,
    longitude: -1.5436,
    next_event: null,
    ...overrides,
  }
}

const venues: Venue[] = [
  makeVenue({ id: 1, name: 'Le Ferrailleur', slug: 'le-ferrailleur', mood: 'festif' }),
  makeVenue({ id: 2, name: 'Café Cult', slug: 'cafe-cult', mood: 'chill' }),
  makeVenue({ id: 3, name: 'Warehouse', slug: 'warehouse', mood: 'festif' }),
]

function mountExplorer() {
  return mount(ExplorerPage, { global: { stubs: { RouterLink: RouterLinkStub } } })
}

beforeEach(() => {
  vi.clearAllMocks()
  fetchVenuesMock.mockResolvedValue(venues)
  // jsdom n'implémente pas scrollIntoView (appelé à la sélection).
  Element.prototype.scrollIntoView = vi.fn()
})

describe('ExplorerPage', () => {
  it('lists every venue in the sidebar', async () => {
    const wrapper = mountExplorer()
    await flushPromises()
    expect(wrapper.findAll('[data-testid="explorer-venue"]')).toHaveLength(3)
  })

  it('filters the list by mood', async () => {
    const wrapper = mountExplorer()
    await flushPromises()

    await wrapper.find('[data-testid="explorer-mood-filter"][data-mood="festif"]').trigger('click')

    const items = wrapper.findAll('[data-testid="explorer-venue"]')
    expect(items).toHaveLength(2)
    expect(wrapper.text()).not.toContain('Café Cult')
  })

  it('filters the list by search query', async () => {
    const wrapper = mountExplorer()
    await flushPromises()

    await wrapper.find('input[type="search"]').setValue('cult')

    const items = wrapper.findAll('[data-testid="explorer-venue"]')
    expect(items).toHaveLength(1)
    expect(items[0]!.text()).toContain('Café Cult')
  })

  it('marks a venue as selected when its card is clicked', async () => {
    const wrapper = mountExplorer()
    await flushPromises()

    const card = wrapper.find('[data-venue-slug="warehouse"]')
    await card.find('button').trigger('click')

    expect(card.find('button').attributes('aria-pressed')).toBe('true')
  })

  it('shows an empty state when no venue matches', async () => {
    const wrapper = mountExplorer()
    await flushPromises()

    await wrapper.find('input[type="search"]').setValue('zzzznomatch')

    expect(wrapper.find('[data-testid="explorer-empty"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid="explorer-venue"]')).toHaveLength(0)
  })
})
