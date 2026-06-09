import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import VenueList from './VenueList.vue'
import type { Venue } from '@/types/venue'

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
    const wrapper = mount(VenueList, { props: { venues } })
    expect(wrapper.findAll('[data-testid="venue-item"]')).toHaveLength(5)
  })

  it('renders the venue names', () => {
    const wrapper = mount(VenueList, { props: { venues } })
    expect(wrapper.text()).toContain('Le Lieu Unique')
    expect(wrapper.text()).toContain('Café Cult')
    expect(wrapper.text()).toContain('Lieu Mystère')
  })

  it('applies the matching bg-mood-* class to each mood dot', () => {
    const wrapper = mount(VenueList, { props: { venues } })
    const dots = wrapper.findAll('[data-testid="venue-mood-dot"]')

    expect(dots[0]!.classes()).toContain('bg-mood-festif')
    expect(dots[1]!.classes()).toContain('bg-mood-chill')
    expect(dots[2]!.classes()).toContain('bg-mood-decouverte')
    expect(dots[3]!.classes()).toContain('bg-mood-afterwork')
  })

  it('falls back to a neutral dot class for a null mood', () => {
    const wrapper = mount(VenueList, { props: { venues } })
    const dots = wrapper.findAll('[data-testid="venue-mood-dot"]')

    expect(dots[4]!.classes()).toContain('bg-white/20')
    expect(dots[4]!.classes()).not.toContain('bg-mood-festif')
  })

  it('renders nothing when given an empty list', () => {
    const wrapper = mount(VenueList, { props: { venues: [] } })
    expect(wrapper.findAll('[data-testid="venue-item"]')).toHaveLength(0)
  })
})
