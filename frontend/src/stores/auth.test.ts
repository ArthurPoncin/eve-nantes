import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from './auth'
import { getToken } from '@/api/token-storage'

const server = setupServer()

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

const session = {
  token: 'tok_abc',
  user: { id: 1, username: 'noctambule', email: 'eve@nantes.fr' },
}

describe('auth store', () => {
  it('stores the session after a successful login', async () => {
    server.use(http.post('*/api/v1/auth/login', () => HttpResponse.json(session)))

    const auth = useAuthStore()
    await auth.login({ email: 'eve@nantes.fr', password: 'password123' })

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.token).toBe('tok_abc')
    expect(auth.user).toEqual(session.user)
  })

  it('attaches the bearer token to authenticated requests', async () => {
    server.use(http.post('*/api/v1/auth/login', () => HttpResponse.json(session)))
    let authHeader: string | null = null
    server.use(
      http.get('*/api/v1/auth/me', ({ request }) => {
        authHeader = request.headers.get('authorization')
        return HttpResponse.json(session.user)
      }),
    )

    const auth = useAuthStore()
    await auth.login({ email: 'eve@nantes.fr', password: 'password123' })
    await auth.loadMe()

    expect(authHeader).toBe('Bearer tok_abc')
  })

  it('restores the token from localStorage on init', () => {
    localStorage.setItem('noctambule.token', 'tok_persisted')

    const auth = useAuthStore()

    expect(auth.token).toBe('tok_persisted')
    expect(auth.isAuthenticated).toBe(true)
  })

  it('clears the session and revokes the token on logout', async () => {
    server.use(http.post('*/api/v1/auth/login', () => HttpResponse.json(session)))
    let logoutCalled = false
    server.use(
      http.post('*/api/v1/auth/logout', () => {
        logoutCalled = true
        return HttpResponse.json({ message: 'Déconnecté' })
      }),
    )

    const auth = useAuthStore()
    await auth.login({ email: 'eve@nantes.fr', password: 'password123' })
    await auth.logout()

    expect(logoutCalled).toBe(true)
    expect(auth.isAuthenticated).toBe(false)
    expect(auth.user).toBeNull()
    expect(getToken()).toBeNull()
  })

  it('stores the session after a successful registration', async () => {
    server.use(
      http.post('*/api/v1/auth/register', () =>
        HttpResponse.json(session, { status: 201 }),
      ),
    )

    const auth = useAuthStore()
    await auth.register({
      username: 'noctambule',
      email: 'eve@nantes.fr',
      password: 'password123',
      password_confirmation: 'password123',
    })

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.token).toBe('tok_abc')
  })
})
