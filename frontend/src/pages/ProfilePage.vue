<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const initial = computed(() => auth.user?.username?.charAt(0).toUpperCase() ?? '?')

onMounted(async () => {
  if (auth.user === null) {
    try {
      await auth.loadMe()
    } catch {
      // Token invalide ou expiré : on laisse la session en l'état, l'affichage retombe sur les valeurs vides.
    }
  }
})

async function onLogout(): Promise<void> {
  await auth.logout()
  await router.push('/')
}
</script>

<template>
  <main class="flex min-h-[80vh] items-center justify-center px-6 py-12">
    <section
      class="glass-strong relative w-full max-w-sm overflow-hidden rounded-3xl border border-hairline bg-glass-strong p-8"
      aria-label="Profil"
    >
      <div
        aria-hidden="true"
        class="pointer-events-none absolute -left-16 -top-20 h-48 w-48 rounded-full bg-violet opacity-25 blur-3xl"
      />

      <div class="relative flex items-center gap-4">
        <span
          class="glow-violet flex h-14 w-14 items-center justify-center rounded-full bg-violet-bright font-serif text-2xl italic text-white"
          aria-hidden="true"
        >
          {{ initial }}
        </span>
        <div class="flex flex-col">
          <p class="font-mono text-[10px] uppercase tracking-[0.3em] text-text-3">Mon profil</p>
          <h1 class="font-serif text-3xl italic text-text">
            {{ auth.user?.username ?? 'Noctambule' }}
          </h1>
        </div>
      </div>

      <dl class="relative mt-8 flex flex-col gap-4">
        <div class="flex flex-col gap-1 rounded-2xl border border-hairline bg-glass px-4 py-3">
          <dt class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">Pseudo</dt>
          <dd data-testid="profile-username" class="text-text">{{ auth.user?.username }}</dd>
        </div>
        <div class="flex flex-col gap-1 rounded-2xl border border-hairline bg-glass px-4 py-3">
          <dt class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">Email</dt>
          <dd data-testid="profile-email" class="text-text">{{ auth.user?.email }}</dd>
        </div>
      </dl>

      <button
        type="button"
        class="relative mt-8 w-full rounded-full border border-hairline px-4 py-3 font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:border-pink/50 hover:text-pink"
        @click="onLogout"
      >
        Déconnexion
      </button>
    </section>
  </main>
</template>
