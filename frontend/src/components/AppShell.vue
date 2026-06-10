<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import MobileNav from './MobileNav.vue'
import ModeToggle from './ModeToggle.vue'
import UserMenu from './UserMenu.vue'

const auth = useAuthStore()
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
          class="hidden font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:text-text sm:inline"
        >
          Carte
        </RouterLink>
        <RouterLink
          v-if="!auth.isAuthenticated"
          to="/login"
          class="glow-pink rounded-full bg-pink px-4 py-1.5 font-mono text-[11px] uppercase tracking-[0.18em] text-white transition hover:bg-pink-bright"
        >
          Connexion
        </RouterLink>
        <ModeToggle />
        <!-- Profil, favoris et déconnexion sont regroupés dans le menu avatar. -->
        <UserMenu v-if="auth.isAuthenticated" />
        <!-- En mobile, Soirée et Carte passent dans le burger. -->
        <MobileNav />
      </nav>
    </header>
    <slot />
  </div>
</template>
