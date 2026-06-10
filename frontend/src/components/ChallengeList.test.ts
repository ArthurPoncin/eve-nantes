import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { Challenge } from '@/types/challenge'

vi.mock('@/api/challenges', () => ({
  fetchChallenges: vi.fn(),
}))

import ChallengeList from './ChallengeList.vue'
import { fetchChallenges } from '@/api/challenges'

const mockedFetchChallenges = vi.mocked(fetchChallenges)

function makeChallenge(overrides: Partial<Challenge> = {}): Challenge {
  return {
    id: 'explorateur-du-mois',
    label: 'Explorateur du mois',
    description: 'Explore 5 nouveaux lieux ce mois-ci',
    icon: '◈',
    goal: 5,
    progress: 3,
    completed: false,
    completed_at: null,
    ends_at: '2026-06-30T23:59:59+02:00',
    ...overrides,
  }
}

async function mountList() {
  const wrapper = mount(ChallengeList)
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  mockedFetchChallenges.mockReset()
})

describe('ChallengeList', () => {
  it('renders each challenge with its progress counter', async () => {
    mockedFetchChallenges.mockResolvedValue([
      makeChallenge(),
      makeChallenge({ id: 'marathonien', label: 'Marathonien', progress: 0, goal: 3 }),
    ])
    const wrapper = await mountList()

    const items = wrapper.findAll('[data-testid="challenge-item"]')
    expect(items).toHaveLength(2)
    expect(items[0]!.find('[data-testid="challenge-progress"]').text()).toBe('3/5')
    expect(items[1]!.find('[data-testid="challenge-progress"]').text()).toBe('0/3')
  })

  it('marks a completed challenge', async () => {
    mockedFetchChallenges.mockResolvedValue([
      makeChallenge({ progress: 5, completed: true }),
    ])
    const wrapper = await mountList()

    const item = wrapper.find('[data-testid="challenge-item"]')
    expect(item.classes()).toContain('glow-gold')
    expect(item.find('[data-testid="challenge-progress"]').text()).toContain('Bouclé')
  })

  it('hides itself when the API fails', async () => {
    mockedFetchChallenges.mockRejectedValue(new Error('401'))
    const wrapper = await mountList()

    expect(wrapper.find('[data-testid="challenge-list"]').exists()).toBe(false)
  })

  it('hides itself when no challenge is active', async () => {
    mockedFetchChallenges.mockResolvedValue([])
    const wrapper = await mountList()

    expect(wrapper.find('[data-testid="challenge-list"]').exists()).toBe(false)
  })
})
