import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { fetchMe, loginRequest, logoutRequest, registerRequest } from '@/api/auth'
import { clearToken, getToken, setToken } from '@/api/token-storage'
import type { AuthUser, Credentials, RegisterPayload } from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(getToken())

  const isAuthenticated = computed(() => token.value !== null)

  function setSession(nextToken: string, nextUser: AuthUser): void {
    token.value = nextToken
    user.value = nextUser
    setToken(nextToken)
  }

  function clearSession(): void {
    token.value = null
    user.value = null
    clearToken()
  }

  async function login(credentials: Credentials): Promise<void> {
    const { token: nextToken, user: nextUser } = await loginRequest(credentials)
    setSession(nextToken, nextUser)
  }

  async function register(payload: RegisterPayload): Promise<void> {
    const { token: nextToken, user: nextUser } = await registerRequest(payload)
    setSession(nextToken, nextUser)
  }

  async function logout(): Promise<void> {
    try {
      await logoutRequest()
    } catch {
      // On nettoie la session locale même si l'appel backend échoue (token déjà expiré, etc.).
    } finally {
      clearSession()
    }
  }

  async function loadMe(): Promise<void> {
    user.value = await fetchMe()
  }

  return { user, token, isAuthenticated, login, register, logout, loadMe }
})
