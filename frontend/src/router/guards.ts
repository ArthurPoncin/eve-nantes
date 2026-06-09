import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
  }
}

export function requireAuth(to: RouteLocationNormalized): true | RouteLocationRaw {
  if (!to.meta.requiresAuth) {
    return true
  }

  const auth = useAuthStore()
  if (!auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  return true
}
