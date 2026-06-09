<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ModeToggle from './ModeToggle.vue'

const auth = useAuthStore()
const router = useRouter()

async function onLogout(): Promise<void> {
  await auth.logout()
  await router.push('/')
}
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <header
      class="glass-strong sticky top-0 z-50 flex items-center justify-between border-b border-hairline bg-glass-strong px-6 py-4"
    >
      <RouterLink to="/" class="font-serif text-2xl italic tracking-tight text-text">
        NOCTAMBULE
      </RouterLink>
      <nav class="flex items-center gap-5">
        <RouterLink
          to="/soiree"
          class="hidden font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:text-text sm:inline"
        >
          Soirée
        </RouterLink>
        <RouterLink
          to="/explorer"
          class="font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:text-text"
        >
          Carte
        </RouterLink>
        <template v-if="auth.isAuthenticated">
          <span
            class="hidden font-mono text-[11px] uppercase tracking-[0.18em] text-text-3 sm:inline"
          >
            {{ auth.user?.username }}
          </span>
          <RouterLink
            to="/favoris"
            class="font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:text-text"
          >
            Favoris
          </RouterLink>
          <RouterLink
            to="/profil"
            class="font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:text-text"
          >
            Profil
          </RouterLink>
          <button
            type="button"
            class="font-mono text-[11px] uppercase tracking-[0.18em] text-text-3 transition hover:text-pink-bright"
            @click="onLogout"
          >
            Déconnexion
          </button>
        </template>
        <RouterLink
          v-else
          to="/login"
          class="glow-pink rounded-full bg-pink px-4 py-1.5 font-mono text-[11px] uppercase tracking-[0.18em] text-white transition hover:bg-pink-bright"
        >
          Connexion
        </RouterLink>
        <ModeToggle />
      </nav>
    </header>
    <slot />
  </div>
</template>
