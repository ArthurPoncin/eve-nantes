import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@/api/stats', () => ({
  fetchMyStats: vi.fn(),
  fetchPilier: vi.fn(),
}))

import PilierCard from './PilierCard.vue'
import { fetchPilier } from '@/api/stats'

const mockedFetchPilier = vi.mocked(fetchPilier)

async function mountCard() {
  const wrapper = mount(PilierCard, { props: { slug: 'le-chat-noir' } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  mockedFetchPilier.mockReset()
})

describe('PilierCard', () => {
  it('crowns the top check-iner', async () => {
    mockedFetchPilier.mockResolvedValue({
      username: 'reine-de-la-nuit',
      checkins_count: 7,
      first_checkin_at: '2026-03-12T21:00:00+01:00',
    })
    const wrapper = await mountCard()

    expect(mockedFetchPilier).toHaveBeenCalledWith('le-chat-noir')
    expect(wrapper.find('[data-testid="pilier-username"]').text()).toBe(
      'reine-de-la-nuit',
    )
    expect(wrapper.text()).toContain('7 passages')
  })

  it('shows the empty throne when nobody qualifies', async () => {
    mockedFetchPilier.mockResolvedValue(null)
    const wrapper = await mountCard()

    expect(wrapper.find('[data-testid="pilier-empty"]').text()).toContain(
      'Le trône est libre',
    )
  })

  it('hides itself when the API fails', async () => {
    mockedFetchPilier.mockRejectedValue(new Error('500'))
    const wrapper = await mountCard()

    expect(wrapper.find('[data-testid="pilier-card"]').exists()).toBe(false)
  })
})
