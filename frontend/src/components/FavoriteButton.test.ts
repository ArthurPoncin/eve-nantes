import { beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import FavoriteButton from './FavoriteButton.vue'
import type { Venue } from '@/types/venue'

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

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

describe('FavoriteButton', () => {
  it('renders nothing when the user is not authenticated', () => {
    const wrapper = mount(FavoriteButton, { props: { venue: makeVenue() } })

    expect(wrapper.find('[data-testid="favorite-button"]').exists()).toBe(false)
  })

  it('renders the toggle button when the user is authenticated', () => {
    // The auth store reads the persisted token on creation; seed it first so
    // the freshly-created store (mounted below) reports isAuthenticated.
    localStorage.setItem('noctambule.token', 'tok_abc')
    setActivePinia(createPinia())

    const wrapper = mount(FavoriteButton, { props: { venue: makeVenue() } })

    const button = wrapper.find('[data-testid="favorite-button"]')
    expect(button.exists()).toBe(true)
    expect(button.attributes('aria-pressed')).toBe('false')
    expect(button.attributes('aria-label')).toBe('Ajouter des favoris')
  })
})
