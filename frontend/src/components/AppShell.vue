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
    <header class="flex items-center justify-between border-b border-white/5 px-6 py-4">
      <RouterLink to="/" class="font-serif text-2xl italic text-ink-primary">
        NOCTAMBULE
      </RouterLink>
      <div class="flex items-center gap-4">
        <template v-if="auth.isAuthenticated">
          <span class="font-mono text-xs uppercase tracking-widest text-ink-muted">
            {{ auth.user?.username }}
          </span>
          <button
            type="button"
            class="font-mono text-xs uppercase tracking-widest text-ink-muted transition hover:text-ink-primary"
            @click="onLogout"
          >
            Déconnexion
          </button>
        </template>
        <RouterLink
          v-else
          to="/login"
          class="font-mono text-xs uppercase tracking-widest text-ink-muted transition hover:text-ink-primary"
        >
          Connexion
        </RouterLink>
        <ModeToggle />
      </div>
    </header>
    <slot />
  </div>
</template>
