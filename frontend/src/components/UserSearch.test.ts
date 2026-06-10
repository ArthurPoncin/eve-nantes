import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'

vi.mock('@/api/users', () => ({
  searchUsers: vi.fn(),
  fetchProfile: vi.fn(),
  followUser: vi.fn(),
  unfollowUser: vi.fn(),
  fetchFollowers: vi.fn(),
  fetchFollowing: vi.fn(),
}))

import UserSearch from './UserSearch.vue'
import { searchUsers } from '@/api/users'

const mockedSearch = vi.mocked(searchUsers)

function mountSearch() {
  return mount(UserSearch, {
    global: { stubs: { RouterLink: RouterLinkStub } },
    attachTo: document.body,
  })
}

beforeEach(() => {
  vi.useFakeTimers()
  mockedSearch.mockReset()
})

afterEach(() => {
  vi.useRealTimers()
  document.body.innerHTML = ''
})

describe('UserSearch', () => {
  it('does not query under two characters', async () => {
    const wrapper = mountSearch()

    await wrapper.find('[data-testid="user-search-input"]').setValue('n')
    vi.advanceTimersByTime(400)
    await flushPromises()

    expect(mockedSearch).not.toHaveBeenCalled()
  })

  it('debounces then renders the results', async () => {
    mockedSearch.mockResolvedValue([
      { id: 2, username: 'noctambule44', followers_count: 3, is_following: false },
    ])
    const wrapper = mountSearch()

    await wrapper.find('[data-testid="user-search-input"]').setValue('noct')
    expect(mockedSearch).not.toHaveBeenCalled()

    vi.advanceTimersByTime(300)
    await flushPromises()

    expect(mockedSearch).toHaveBeenCalledWith('noct')
    const results = wrapper.findAll('[data-testid="user-search-result"]')
    expect(results).toHaveLength(1)
    expect(results[0]!.text()).toContain('noctambule44')
    expect(results[0]!.text()).toContain('3 abonnés')
  })

  it('only fires once for a fast typing burst', async () => {
    mockedSearch.mockResolvedValue([])
    const wrapper = mountSearch()
    const input = wrapper.find('[data-testid="user-search-input"]')

    await input.setValue('no')
    vi.advanceTimersByTime(100)
    await input.setValue('noct')
    vi.advanceTimersByTime(300)
    await flushPromises()

    expect(mockedSearch).toHaveBeenCalledTimes(1)
    expect(mockedSearch).toHaveBeenCalledWith('noct')
  })
})
