import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { requireAuth } from './guards'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'landing',
    component: () => import('@/pages/LandingPage.vue'),
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/pages/LoginPage.vue'),
  },
  {
    path: '/profil',
    name: 'profil',
    component: () => import('@/pages/ProfilePage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/favoris',
    name: 'favoris',
    component: () => import('@/pages/FavoritesPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/venues/:slug',
    name: 'venue-detail',
    component: () => import('@/pages/VenueDetailPage.vue'),
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(requireAuth)
