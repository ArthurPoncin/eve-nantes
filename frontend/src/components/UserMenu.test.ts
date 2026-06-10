import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

const { push } = vi.hoisted(() => ({ push: vi.fn() }))
vi.mock('vue-router', async () => {
  const { RouterLinkStub } = await import('@vue/test-utils')
  return { useRouter: () => ({ push }), RouterLink: RouterLinkStub }
})

vi.mock('@/api/auth', () => ({
  fetchMe: vi.fn(),
  loginRequest: vi.fn(),
  logoutRequest: vi.fn().mockResolvedValue(undefined),
  registerRequest: vi.fn(),
}))

vi.mock('@/api/badges', () => ({ fetchBadges: vi.fn() }))

import { flushPromises } from '@vue/test-utils'
import { fetchBadges } from '@/api/badges'
import type { Badge } from '@/types/badge'
import UserMenu from './UserMenu.vue'

const mockedFetchBadges = vi.mocked(fetchBadges)

function makeBadge(overrides: Partial<Badge> = {}): Badge {
  return {
    id: 'critique',
    label: 'Critique',
    description: 'Poster son premier avis',
    icon: '☆',
    unlocked: false,
    unlocked_at: null,
    ...overrides,
  }
}

function mountMenu() {
  const wrapper = mount(UserMenu, {
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
  const auth = useAuthStore()
  auth.user = { id: 1, username: 'arthur', email: 'arthur@example.com' }
  return { wrapper, auth }
}

beforeEach(() => {
  localStorage.clear()
  localStorage.setItem('noctambule.token', 'tok_abc')
  setActivePinia(createPinia())
  push.mockClear()
  mockedFetchBadges.mockReset()
  mockedFetchBadges.mockRejectedValue(new Error('non mocké'))
})

describe('UserMenu', () => {
  it("affiche l'initiale du pseudo sur l'avatar, menu fermé par défaut", async () => {
    const { wrapper } = mountMenu()
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-testid="user-menu-button"]').text()).toBe('A')
    expect(wrapper.find('[data-testid="user-menu"]').exists()).toBe(false)
  })

  it('ouvre le menu au clic avec pseudo, email et les trois entrées', async () => {
    const { wrapper } = mountMenu()
    await wrapper.find('[data-testid="user-menu-button"]').trigger('click')

    const menu = wrapper.find('[data-testid="user-menu"]')
    expect(menu.exists()).toBe(true)
    expect(menu.text()).toContain('arthur')
    expect(menu.text()).toContain('arthur@example.com')
    expect(wrapper.find('[data-testid="user-menu-profile"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="user-menu-favorites"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="user-menu-logout"]').exists()).toBe(true)
  })

  it('pointe vers /profil, le profil public et /favoris', async () => {
    const { wrapper } = mountMenu()
    await wrapper.find('[data-testid="user-menu-button"]').trigger('click')

    const links = wrapper.findAllComponents(RouterLinkStub)
    expect(links.map((link) => link.props('to'))).toEqual([
      '/profil',
      '/u/arthur',
      '/favoris',
    ])
  })

  it('déconnecte puis redirige vers l’accueil', async () => {
    const { wrapper, auth } = mountMenu()
    await wrapper.find('[data-testid="user-menu-button"]').trigger('click')
    await wrapper.find('[data-testid="user-menu-logout"]').trigger('click')
    await wrapper.vm.$nextTick()

    expect(auth.isAuthenticated).toBe(false)
    expect(push).toHaveBeenCalledWith('/')
  })

  it('ferme le menu avec Échap', async () => {
    const { wrapper } = mountMenu()
    await wrapper.find('[data-testid="user-menu-button"]').trigger('click')
    expect(wrapper.find('[data-testid="user-menu"]').exists()).toBe(true)

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-testid="user-menu"]').exists()).toBe(false)
  })

  it('ferme le menu au clic en dehors', async () => {
    const { wrapper } = mountMenu()
    await wrapper.find('[data-testid="user-menu-button"]').trigger('click')

    document.body.click()
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-testid="user-menu"]').exists()).toBe(false)
  })

  it('ferme le menu quand on suit un lien', async () => {
    const { wrapper } = mountMenu()
    await wrapper.find('[data-testid="user-menu-button"]').trigger('click')

    await wrapper.find('[data-testid="user-menu-profile"]').trigger('click')

    expect(wrapper.find('[data-testid="user-menu"]').exists()).toBe(false)
  })

  it('affiche le compteur de badges débloqués à l’ouverture', async () => {
    mockedFetchBadges.mockResolvedValue([
      makeBadge({ id: 'critique', unlocked: true }),
      makeBadge({ id: 'noctambule' }),
      makeBadge({ id: 'explorateur' }),
    ])
    const { wrapper } = mountMenu()

    expect(mockedFetchBadges).not.toHaveBeenCalled()

    await wrapper.find('[data-testid="user-menu-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="user-menu-badges"]').text()).toContain('1/3')
  })

  it('ne recharge pas les badges aux ouvertures suivantes', async () => {
    mockedFetchBadges.mockResolvedValue([makeBadge({ unlocked: true })])
    const { wrapper } = mountMenu()
    const button = wrapper.find('[data-testid="user-menu-button"]')

    await button.trigger('click')
    await flushPromises()
    await button.trigger('click')
    await button.trigger('click')
    await flushPromises()

    expect(mockedFetchBadges).toHaveBeenCalledTimes(1)
  })

  it('masque la ligne badges quand le chargement échoue', async () => {
    mockedFetchBadges.mockRejectedValue(new Error('500'))
    const { wrapper } = mountMenu()
    await wrapper.find('[data-testid="user-menu-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="user-menu"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="user-menu-badges"]').exists()).toBe(false)
  })
})
