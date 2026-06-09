import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import type { Soiree } from '@/types/soiree'

const generateSoireeMock = vi.fn()
const shareSoireeMock = vi.fn()
vi.mock('@/api/soiree', () => ({
  generateSoiree: (...args: unknown[]) => generateSoireeMock(...args),
  shareSoiree: (...args: unknown[]) => shareSoireeMock(...args),
}))

import SoireePage from './SoireePage.vue'

const sampleSoiree: Soiree = {
  mood: 'festif',
  venue: {
    id: 1,
    name: 'Le Macadam',
    slug: 'le-macadam',
    address_line: '1 Rue Test',
    postal_code: '44000',
    city: 'Nantes',
    mood: 'festif',
    capacity: null,
    latitude: 47.21,
    longitude: -1.55,
    next_event: null,
  },
  event: {
    id: 9,
    title: 'Nuit Techno',
    slug: 'nuit-techno',
    description: 'Set.',
    starts_at: '2026-06-14T21:00:00.000Z',
    ends_at: '2026-06-15T02:00:00.000Z',
    price_cents: 1200,
  },
  weather: { temp: 18.4, feels_like: 17, condition: 'Couvert', icon: '04n', wind: 8, humidity: 70 },
  narrative: 'Ce soir au Macadam, la techno vibre sous un ciel couvert.',
}

function mountPage() {
  return mount(SoireePage, { global: { stubs: { RouterLink: RouterLinkStub } } })
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('SoireePage', () => {
  it('shows the four mood choices', () => {
    const wrapper = mountPage()
    expect(wrapper.findAll('[data-testid="soiree-mood"]')).toHaveLength(4)
  })

  it('composes a soiree when a mood is chosen', async () => {
    generateSoireeMock.mockResolvedValue(sampleSoiree)
    const wrapper = mountPage()

    await wrapper.find('[data-testid="soiree-mood"][data-mood="festif"]').trigger('click')
    await flushPromises()

    expect(generateSoireeMock).toHaveBeenCalledWith('festif')
    const result = wrapper.find('[data-testid="soiree-result"]')
    expect(result.exists()).toBe(true)
    expect(wrapper.find('[data-testid="soiree-narrative"]').text()).toContain('la techno vibre')
    expect(result.text()).toContain('Le Macadam')
    expect(result.text()).toContain('Nuit Techno')
  })

  it('regenerates with the same mood', async () => {
    generateSoireeMock.mockResolvedValue(sampleSoiree)
    const wrapper = mountPage()
    await wrapper.find('[data-testid="soiree-mood"][data-mood="festif"]').trigger('click')
    await flushPromises()

    await wrapper.find('[data-testid="soiree-regenerate"]').trigger('click')
    await flushPromises()

    expect(generateSoireeMock).toHaveBeenCalledTimes(2)
    expect(generateSoireeMock).toHaveBeenLastCalledWith('festif')
  })

  it('shows an empty state when no venue matches (404)', async () => {
    generateSoireeMock.mockRejectedValue({ response: { status: 404 } })
    const wrapper = mountPage()

    await wrapper.find('[data-testid="soiree-mood"][data-mood="chill"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="soiree-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="soiree-result"]').exists()).toBe(false)
  })

  it('shows an error state on failure', async () => {
    generateSoireeMock.mockRejectedValue(new Error('network'))
    const wrapper = mountPage()

    await wrapper.find('[data-testid="soiree-mood"][data-mood="afterwork"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="soiree-error"]').exists()).toBe(true)
  })

  it('shares the composed soiree by email', async () => {
    generateSoireeMock.mockResolvedValue(sampleSoiree)
    shareSoireeMock.mockResolvedValue(undefined)
    const wrapper = mountPage()
    await wrapper.find('[data-testid="soiree-mood"][data-mood="festif"]').trigger('click')
    await flushPromises()

    await wrapper.find('[data-testid="soiree-share-toggle"]').trigger('click')
    await wrapper.find('[data-testid="soiree-share-form"] input').setValue('ami@example.com')
    await wrapper.find('[data-testid="soiree-share-form"]').trigger('submit')
    await flushPromises()

    expect(shareSoireeMock).toHaveBeenCalledWith(
      expect.objectContaining({
        email: 'ami@example.com',
        mood: 'festif',
        venue_id: 1,
        event_id: 9,
        narrative: expect.stringContaining('la techno vibre'),
      }),
    )
    expect(wrapper.find('[data-testid="soiree-share-sent"]').exists()).toBe(true)
  })
})
