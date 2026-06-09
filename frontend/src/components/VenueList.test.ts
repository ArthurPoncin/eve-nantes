import { beforeEach, describe, expect, it } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import VenueList from './VenueList.vue'
import type { Venue } from '@/types/venue'

// VenueList renders FavoriteButton, which reads the auth + favorites Pinia
// stores, so mounting now requires an active Pinia. Unauthenticated (no token
// in localStorage) the button renders nothing, so existing assertions hold.
beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

function mountVenueList(venues: Venue[]) {
  return mount(VenueList, {
    props: { venues },
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
}

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
    ...overrides,
  }
}

describe('VenueList', () => {
  const venues: Venue[] = [
    makeVenue({ id: 1, name: 'Le Lieu Unique', mood: 'festif' }),
    makeVenue({ id: 2, name: 'Café Cult', mood: 'chill' }),
    makeVenue({ id: 3, name: 'La Cantine', mood: 'decouverte' }),
    makeVenue({ id: 4, name: 'Le Nid', mood: 'afterwork' }),
    makeVenue({ id: 5, name: 'Lieu Mystère', mood: null }),
  ]

  it('renders one item per venue', () => {
    const wrapper = mountVenueList(venues)
    expect(wrapper.findAll('[data-testid="venue-item"]')).toHaveLength(5)
  })

  it('renders the venue names', () => {
    const wrapper = mountVenueList(venues)
    expect(wrapper.text()).toContain('Le Lieu Unique')
    expect(wrapper.text()).toContain('Café Cult')
    expect(wrapper.text()).toContain('Lieu Mystère')
  })

  it('applies the matching bg-mood-* class to each mood dot', () => {
    const wrapper = mountVenueList(venues)
    const dots = wrapper.findAll('[data-testid="venue-mood-dot"]')

    expect(dots[0]!.classes()).toContain('bg-mood-festif')
    expect(dots[1]!.classes()).toContain('bg-mood-chill')
    expect(dots[2]!.classes()).toContain('bg-mood-decouverte')
    expect(dots[3]!.classes()).toContain('bg-mood-afterwork')
  })

  it('falls back to a neutral dot class for a null mood', () => {
    const wrapper = mountVenueList(venues)
    const dots = wrapper.findAll('[data-testid="venue-mood-dot"]')

    expect(dots[4]!.classes()).toContain('bg-white/20')
    expect(dots[4]!.classes()).not.toContain('bg-mood-festif')
  })

  it('links each venue to its detail page', () => {
    const wrapper = mountVenueList(venues)
    const links = wrapper.findAllComponents(RouterLinkStub)

    expect(links).toHaveLength(5)
    expect(links[0]!.props('to')).toBe('/venues/le-lieu-unique')
  })

  it('renders nothing when given an empty list', () => {
    const wrapper = mountVenueList([])
    expect(wrapper.findAll('[data-testid="venue-item"]')).toHaveLength(0)
  })

  it('shows the next upcoming event when the venue has one', () => {
    const wrapper = mountVenueList([
      makeVenue({
        id: 9,
        name: 'Stereolux',
        next_event: {
          id: 1,
          title: 'Soirée Techno',
          slug: 'soiree-techno',
          description: 'Une nuit electro.',
          starts_at: '2026-06-14T21:00:00.000Z',
          ends_at: '2026-06-15T02:00:00.000Z',
          price_cents: 1500,
        },
      }),
    ])

    const footer = wrapper.find('[data-testid="venue-next-event"]')
    expect(footer.exists()).toBe(true)
    expect(footer.text()).toContain('Soirée Techno')
  })

  it('omits the event footer when the venue has no upcoming event', () => {
    const wrapper = mountVenueList([makeVenue({ id: 10, next_event: null })])
    expect(wrapper.find('[data-testid="venue-next-event"]').exists()).toBe(false)
  })
})
