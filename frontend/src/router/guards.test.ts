import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { RouteLocationNormalized } from 'vue-router'
import { requireAuth } from './guards'

function fakeRoute(meta: RouteLocationNormalized['meta'], fullPath = '/'): RouteLocationNormalized {
  return { meta, fullPath } as RouteLocationNormalized
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

describe('requireAuth', () => {
  it('allows navigation when the route does not require auth', () => {
    const result = requireAuth(fakeRoute({}, '/'))

    expect(result).toBe(true)
  })

  it('redirects to login with the redirect query when not authenticated', () => {
    const result = requireAuth(fakeRoute({ requiresAuth: true }, '/profil'))

    expect(result).toEqual({ name: 'login', query: { redirect: '/profil' } })
  })

  it('allows navigation when the route requires auth and the user is authenticated', () => {
    localStorage.setItem('noctambule.token', 'tok')

    const result = requireAuth(fakeRoute({ requiresAuth: true }, '/profil'))

    expect(result).toBe(true)
  })
})
