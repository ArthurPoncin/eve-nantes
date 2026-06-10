import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { requireAuth } from './guards'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'landing',
    component: () => import('@/pages/LandingPage.vue'),
  },
  {
    path: '/explorer',
    name: 'explorer',
    component: () => import('@/pages/ExplorerPage.vue'),
  },
  {
    path: '/soiree',
    name: 'soiree',
    component: () => import('@/pages/SoireePage.vue'),
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
  {
    // Récap de virée, partageable : public, identifié par UUID.
    path: '/viree/:publicId',
    name: 'viree',
    component: () => import('@/pages/VireePage.vue'),
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(requireAuth)
