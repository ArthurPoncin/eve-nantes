import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@/api/users', () => ({
  searchUsers: vi.fn(),
  fetchProfile: vi.fn(),
  followUser: vi.fn(),
  unfollowUser: vi.fn(),
  fetchFollowers: vi.fn(),
  fetchFollowing: vi.fn(),
}))

import FollowButton from './FollowButton.vue'
import { followUser, unfollowUser } from '@/api/users'

const mockedFollow = vi.mocked(followUser)
const mockedUnfollow = vi.mocked(unfollowUser)

beforeEach(() => {
  mockedFollow.mockReset()
  mockedUnfollow.mockReset()
})

describe('FollowButton', () => {
  it('shows « Suivre » and follows on click', async () => {
    mockedFollow.mockResolvedValue({ followers_count: 1 })
    const wrapper = mount(FollowButton, {
      props: { username: 'amie', isFollowing: false },
    })

    const button = wrapper.find('[data-testid="follow-button"]')
    expect(button.text()).toBe('Suivre')

    await button.trigger('click')
    await flushPromises()

    expect(mockedFollow).toHaveBeenCalledWith('amie')
    expect(button.text()).toContain('Suivi')
    expect(wrapper.emitted('change')?.[0]).toEqual([true])
  })

  it('shows « Suivi ✓ » and unfollows on click', async () => {
    mockedUnfollow.mockResolvedValue()
    const wrapper = mount(FollowButton, {
      props: { username: 'amie', isFollowing: true },
    })

    const button = wrapper.find('[data-testid="follow-button"]')
    expect(button.text()).toContain('Suivi')

    await button.trigger('click')
    await flushPromises()

    expect(mockedUnfollow).toHaveBeenCalledWith('amie')
    expect(button.text()).toBe('Suivre')
  })

  it('rolls back when the API refuses', async () => {
    mockedFollow.mockRejectedValue(new Error('422'))
    const wrapper = mount(FollowButton, {
      props: { username: 'amie', isFollowing: false },
    })

    await wrapper.find('[data-testid="follow-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="follow-button"]').text()).toBe('Suivre')
    expect(wrapper.emitted('change')).toBeUndefined()
  })
})
