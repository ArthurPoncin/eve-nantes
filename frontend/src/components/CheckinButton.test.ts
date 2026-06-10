import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { Viree } from '@/types/viree'

vi.mock('@/api/virees', () => ({
  checkIn: vi.fn(),
  fetchCurrentViree: vi.fn(),
  closeViree: vi.fn(),
  fetchVirees: vi.fn(),
  fetchViree: vi.fn(),
}))

import CheckinButton from './CheckinButton.vue'
import { checkIn } from '@/api/virees'
import { useVireeStore } from '@/stores/viree'

const mockedCheckIn = vi.mocked(checkIn)

function makeViree(slug: string): Viree {
  return {
    id: 1,
    public_id: 'aaaa-bbbb',
    is_public: true,
    status: 'en_cours',
    started_at: '2026-06-10T21:00:00+02:00',
    ended_at: null,
    stats: { venues: 1, distance_m: null, duration_min: 10, moods: [] },
    narrative: null,
    weather: null,
    checkins: [
      {
        id: 1,
        happened_at: '2026-06-10T21:00:00+02:00',
        venue: { id: 1, name: slug, slug, mood: null, latitude: null, longitude: null },
      },
    ],
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  mockedCheckIn.mockReset()
})

describe('CheckinButton', () => {
  it('renders the call to action when not checked in here', () => {
    const wrapper = mount(CheckinButton, { props: { slug: 'le-macadam' } })

    const button = wrapper.find('[data-testid="venue-checkin"]')
    expect(button.text()).toBe('J’y suis')
    expect(button.attributes('disabled')).toBeUndefined()
  })

  it('checks in and switches to the « here » state', async () => {
    mockedCheckIn.mockResolvedValue(makeViree('le-macadam'))
    const wrapper = mount(CheckinButton, { props: { slug: 'le-macadam' } })

    await wrapper.find('[data-testid="venue-checkin"]').trigger('click')
    await flushPromises()

    expect(mockedCheckIn).toHaveBeenCalledWith('le-macadam')
    const button = wrapper.find('[data-testid="venue-checkin"]')
    expect(button.text()).toBe('Tu es ici ✓')
    expect(button.attributes('disabled')).toBeDefined()
  })

  it('is already disabled when the last check-in is this venue', () => {
    const store = useVireeStore()
    store.current = makeViree('le-macadam')

    const wrapper = mount(CheckinButton, { props: { slug: 'le-macadam' } })

    expect(wrapper.find('[data-testid="venue-checkin"]').text()).toBe('Tu es ici ✓')
  })

  it('stays actionable for another venue while a viree is running', () => {
    const store = useVireeStore()
    store.current = makeViree('le-macadam')

    const wrapper = mount(CheckinButton, { props: { slug: 'stereolux' } })

    expect(wrapper.find('[data-testid="venue-checkin"]').text()).toBe('J’y suis')
  })
})
