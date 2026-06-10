import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { VenueTransport } from '@/types/transport'

vi.mock('@/api/transport', () => ({ fetchVenueTransport: vi.fn() }))

import TransportWidget from './TransportWidget.vue'
import { fetchVenueTransport } from '@/api/transport'

const mockedFetchTransport = vi.mocked(fetchVenueTransport)

function makeTransport(overrides: Partial<VenueTransport> = {}): VenueTransport {
  return {
    stop: { code: 'CDCI', name: 'Chantiers Navals', distance: '143 m' },
    departures: [
      {
        line: '1',
        type: 'tram',
        terminus: 'François Mitterrand',
        wait: '4mn',
        realtime: true,
      },
      {
        line: 'C5',
        type: 'bus',
        terminus: 'Quai des Antilles',
        wait: '12mn',
        realtime: false,
      },
    ],
    ...overrides,
  }
}

async function mountWidget() {
  const wrapper = mount(TransportWidget, { props: { slug: 'le-macadam' } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  mockedFetchTransport.mockReset()
})

describe('TransportWidget', () => {
  it('renders the nearest stop and its departures', async () => {
    mockedFetchTransport.mockResolvedValue(makeTransport())
    const wrapper = await mountWidget()

    expect(mockedFetchTransport).toHaveBeenCalledWith('le-macadam')
    expect(wrapper.find('[data-testid="transport-stop-name"]').text()).toContain(
      'Chantiers Navals',
    )
    expect(wrapper.text()).toContain('143 m')

    const departures = wrapper.findAll('[data-testid="transport-departure"]')
    expect(departures).toHaveLength(2)
    expect(departures[0]!.text()).toContain('François Mitterrand')
    expect(departures[0]!.text()).toContain('4mn')
    expect(departures[1]!.text()).toContain('C5')
  })

  it('flags realtime departures for screen readers', async () => {
    mockedFetchTransport.mockResolvedValue(makeTransport())
    const wrapper = await mountWidget()

    expect(wrapper.findAll('[data-testid="transport-realtime"]')).toHaveLength(1)
  })

  it('shows an empty hint when the stop has no upcoming departure', async () => {
    mockedFetchTransport.mockResolvedValue(makeTransport({ departures: [] }))
    const wrapper = await mountWidget()

    expect(wrapper.find('[data-testid="transport-stop-name"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="transport-empty"]').exists()).toBe(true)
  })

  it('renders nothing when no stop is nearby', async () => {
    mockedFetchTransport.mockResolvedValue(makeTransport({ stop: null, departures: [] }))
    const wrapper = await mountWidget()

    expect(wrapper.find('[data-testid="transport-widget"]').exists()).toBe(false)
  })

  it('renders nothing when the API fails', async () => {
    mockedFetchTransport.mockRejectedValue(new Error('500'))
    const wrapper = await mountWidget()

    expect(wrapper.find('[data-testid="transport-widget"]').exists()).toBe(false)
  })
})
