import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { Badge } from '@/types/badge'

vi.mock('@/api/badges', () => ({ fetchBadges: vi.fn() }))

import BadgeGrid from './BadgeGrid.vue'
import { fetchBadges } from '@/api/badges'

const mockedFetch = vi.mocked(fetchBadges)

function makeBadges(): Badge[] {
  return [
    {
      id: 'critique',
      label: 'Critique',
      description: 'Premier avis posté sur un lieu',
      icon: '☆',
      unlocked: true,
      unlocked_at: '2026-06-10T21:00:00+02:00',
    },
    {
      id: 'noctambule',
      label: 'Noctambule',
      description: '5 soirées composées et partagées',
      icon: '◉',
      unlocked: false,
      unlocked_at: null,
    },
  ]
}

async function mountGrid() {
  const wrapper = mount(BadgeGrid)
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  mockedFetch.mockReset()
})

describe('BadgeGrid', () => {
  it('renders every badge with its label and icon', async () => {
    mockedFetch.mockResolvedValue(makeBadges())
    const wrapper = await mountGrid()

    const items = wrapper.findAll('[data-testid="badge-item"]')
    expect(items).toHaveLength(2)
    expect(items[0]!.text()).toContain('Critique')
    expect(items[0]!.text()).toContain('☆')
    expect(items[1]!.text()).toContain('Noctambule')
  })

  it('distinguishes unlocked badges from locked ones', async () => {
    mockedFetch.mockResolvedValue(makeBadges())
    const wrapper = await mountGrid()

    expect(wrapper.findAll('[data-testid="badge-unlocked"]')).toHaveLength(1)
    expect(wrapper.findAll('[data-testid="badge-locked"]')).toHaveLength(1)
  })

  it('counts the unlocked badges in the header', async () => {
    mockedFetch.mockResolvedValue(makeBadges())
    const wrapper = await mountGrid()

    expect(wrapper.find('[data-testid="badge-count"]').text()).toContain('1/2')
  })

  it('renders nothing when the API fails', async () => {
    mockedFetch.mockRejectedValue(new Error('401'))
    const wrapper = await mountGrid()

    expect(wrapper.find('[data-testid="badge-grid"]').exists()).toBe(false)
  })
})
