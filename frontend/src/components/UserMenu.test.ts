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

import UserMenu from './UserMenu.vue'

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

  it('pointe vers /profil et /favoris', async () => {
    const { wrapper } = mountMenu()
    await wrapper.find('[data-testid="user-menu-button"]').trigger('click')

    const links = wrapper.findAllComponents(RouterLinkStub)
    expect(links.map((link) => link.props('to'))).toEqual(['/profil', '/favoris'])
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
})
