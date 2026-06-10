import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { Viree } from '@/types/viree'

const pushMock = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}))

vi.mock('@/api/virees', () => ({
  checkIn: vi.fn(),
  fetchCurrentViree: vi.fn(),
  closeViree: vi.fn(),
  fetchVirees: vi.fn(),
  fetchViree: vi.fn(),
}))

import VireeBanner from './VireeBanner.vue'
import { closeViree, fetchCurrentViree } from '@/api/virees'

const mockedFetchCurrent = vi.mocked(fetchCurrentViree)
const mockedClose = vi.mocked(closeViree)

function makeViree(overrides: Partial<Viree> = {}): Viree {
  return {
    id: 1,
    public_id: 'aaaa-bbbb',
    is_public: true,
    status: 'en_cours',
    started_at: '2026-06-10T21:14:00+02:00',
    ended_at: null,
    stats: { venues: 3, distance_m: null, duration_min: 90, moods: ['festif'] },
    narrative: null,
    weather: null,
    checkins: [],
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
  pushMock.mockReset()
  mockedFetchCurrent.mockReset()
  mockedClose.mockReset()
})

describe('VireeBanner', () => {
  it('stays hidden for anonymous visitors and does not fetch', async () => {
    const wrapper = mount(VireeBanner)
    await flushPromises()

    expect(mockedFetchCurrent).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="viree-banner"]').exists()).toBe(false)
  })

  it('stays hidden when no viree is running', async () => {
    localStorage.setItem('noctambule.token', 'tok_abc')
    setActivePinia(createPinia())
    mockedFetchCurrent.mockResolvedValue(null)

    const wrapper = mount(VireeBanner)
    await flushPromises()

    expect(wrapper.find('[data-testid="viree-banner"]').exists()).toBe(false)
  })

  it('shows the running viree with its venue count', async () => {
    localStorage.setItem('noctambule.token', 'tok_abc')
    setActivePinia(createPinia())
    mockedFetchCurrent.mockResolvedValue(makeViree())

    const wrapper = mount(VireeBanner)
    await flushPromises()

    const banner = wrapper.find('[data-testid="viree-banner"]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toContain('Virée en cours')
    expect(banner.text()).toContain('3 lieux')
  })

  it('closes the viree and navigates to the recap', async () => {
    localStorage.setItem('noctambule.token', 'tok_abc')
    setActivePinia(createPinia())
    mockedFetchCurrent.mockResolvedValue(makeViree())
    mockedClose.mockResolvedValue(makeViree({ status: 'terminee' }))

    const wrapper = mount(VireeBanner)
    await flushPromises()
    await wrapper.find('[data-testid="viree-banner-close"]').trigger('click')
    await flushPromises()

    expect(mockedClose).toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith('/viree/aaaa-bbbb')
    expect(wrapper.find('[data-testid="viree-banner"]').exists()).toBe(false)
  })
})
