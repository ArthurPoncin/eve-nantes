import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('vue-router', async () => {
  const { RouterLinkStub } = await import('@vue/test-utils')
  return { RouterLink: RouterLinkStub }
})

import { RouterLinkStub } from '@vue/test-utils'
import MobileNav from './MobileNav.vue'

function mountNav() {
  return mount(MobileNav)
}

beforeEach(() => {
  // Le burger lit le store auth (lien « Fil » réservé aux connectés).
  setActivePinia(createPinia())
  localStorage.clear()
  document.body.innerHTML = ''
})

describe('MobileNav', () => {
  it('est fermé par défaut', () => {
    const wrapper = mountNav()

    expect(wrapper.find('[data-testid="mobile-nav-button"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="mobile-nav"]').exists()).toBe(false)
  })

  it('ouvre le panneau avec les liens Soirée et Carte', async () => {
    const wrapper = mountNav()
    await wrapper.find('[data-testid="mobile-nav-button"]').trigger('click')

    expect(wrapper.find('[data-testid="mobile-nav"]').exists()).toBe(true)
    const links = wrapper.findAllComponents(RouterLinkStub)
    expect(links.map((link) => link.props('to'))).toEqual(['/soiree', '/explorer'])
  })

  it('ferme le panneau quand on suit un lien', async () => {
    const wrapper = mountNav()
    await wrapper.find('[data-testid="mobile-nav-button"]').trigger('click')

    await wrapper.find('[data-testid="mobile-nav-soiree"]').trigger('click')

    expect(wrapper.find('[data-testid="mobile-nav"]').exists()).toBe(false)
  })

  it('ferme le panneau avec Échap', async () => {
    const wrapper = mountNav()
    await wrapper.find('[data-testid="mobile-nav-button"]').trigger('click')

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-testid="mobile-nav"]').exists()).toBe(false)
  })

  it('ferme le panneau au clic en dehors', async () => {
    const wrapper = mountNav()
    await wrapper.find('[data-testid="mobile-nav-button"]').trigger('click')

    document.body.click()
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-testid="mobile-nav"]').exists()).toBe(false)
  })

  it('propose le lien Fil quand on est connecté', async () => {
    localStorage.setItem('noctambule.token', 'tok_abc')
    setActivePinia(createPinia())

    const wrapper = mountNav()
    await wrapper.find('[data-testid="mobile-nav-button"]').trigger('click')

    const links = wrapper.findAllComponents(RouterLinkStub)
    expect(links.map((link) => link.props('to'))).toEqual([
      '/feed',
      '/soiree',
      '/explorer',
    ])
  })
})
