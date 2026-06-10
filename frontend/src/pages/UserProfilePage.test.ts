import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { PublicProfile } from '@/types/social'

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { username: 'noctambule44' } }),
  useRouter: () => ({ push: vi.fn() }),
}))

vi.mock('@/api/users', () => ({
  searchUsers: vi.fn(),
  fetchProfile: vi.fn(),
  followUser: vi.fn(),
  unfollowUser: vi.fn(),
  fetchFollowers: vi.fn(),
  fetchFollowing: vi.fn(),
}))

import UserProfilePage from './UserProfilePage.vue'
import { fetchProfile } from '@/api/users'
import { useAuthStore } from '@/stores/auth'

const mockedFetchProfile = vi.mocked(fetchProfile)

function makeProfile(overrides: Partial<PublicProfile> = {}): PublicProfile {
  return {
    username: 'noctambule44',
    member_since: '2026-01-15T00:00:00+01:00',
    badge_count: 3,
    followers_count: 2,
    following_count: 5,
    is_following: false,
    stats: { virees_count: 4, total_km: 12.3, distinct_venues: 6 },
    recent_virees: [],
    ...overrides,
  }
}

async function mountPage() {
  const wrapper = mount(UserProfilePage, {
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
  mockedFetchProfile.mockReset()
})

describe('UserProfilePage', () => {
  it('renders the public profile with its stats', async () => {
    mockedFetchProfile.mockResolvedValue(makeProfile())
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="user-profile-name"]').text()).toBe('noctambule44')
    expect(wrapper.text()).toContain('3 badges')
    const stats = wrapper.find('[data-testid="user-profile-stats"]').text()
    expect(stats).toContain('4')
    expect(stats).toContain('12,3')
    expect(stats).toContain('6')
    expect(wrapper.find('[data-testid="user-profile-followers"]').text()).toContain('2')
  })

  it('offers a login link to anonymous visitors instead of the follow button', async () => {
    mockedFetchProfile.mockResolvedValue(makeProfile({ is_following: null }))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="user-profile-login"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="follow-button"]').exists()).toBe(false)
  })

  it('hides the follow button on my own profile', async () => {
    localStorage.setItem('noctambule.token', 'tok_abc')
    setActivePinia(createPinia())
    const auth = useAuthStore()
    auth.user = { id: 1, username: 'noctambule44', email: 'moi@example.com' }
    mockedFetchProfile.mockResolvedValue(makeProfile({ is_following: false }))

    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="follow-button"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="user-profile-login"]').exists()).toBe(false)
  })

  it('shows the follow button on someone else’s profile when authenticated', async () => {
    localStorage.setItem('noctambule.token', 'tok_abc')
    setActivePinia(createPinia())
    const auth = useAuthStore()
    auth.user = { id: 9, username: 'moi', email: 'moi@example.com' }
    mockedFetchProfile.mockResolvedValue(makeProfile())

    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="follow-button"]').exists()).toBe(true)
  })

  it('shows the error state for an unknown username', async () => {
    mockedFetchProfile.mockRejectedValue(new Error('404'))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="user-profile-error"]').exists()).toBe(true)
  })
})
