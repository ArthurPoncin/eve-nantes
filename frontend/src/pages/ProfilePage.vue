<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

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
  <main class="flex min-h-[80vh] items-center justify-center px-6">
    <section
      class="w-full max-w-sm rounded-2xl border border-white/10 bg-white/5 p-8"
      aria-label="Profil"
    >
      <h1 class="font-serif text-3xl italic text-ink-primary">Mon profil</h1>
      <p class="mt-1 text-sm text-ink-muted">Tes informations NOCTAMBULE.</p>

      <dl class="mt-6 flex flex-col gap-4">
        <div class="flex flex-col gap-1">
          <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">Pseudo</dt>
          <dd data-testid="profile-username" class="text-ink-primary">
            {{ auth.user?.username }}
          </dd>
        </div>
        <div class="flex flex-col gap-1">
          <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">Email</dt>
          <dd data-testid="profile-email" class="text-ink-primary">
            {{ auth.user?.email }}
          </dd>
        </div>
      </dl>

      <button
        type="button"
        class="mt-8 rounded-full bg-ink-primary px-4 py-2 font-mono text-xs uppercase tracking-widest text-surface-night transition hover:opacity-90"
        @click="onLogout"
      >
        Déconnexion
      </button>
    </section>
  </main>
</template>
