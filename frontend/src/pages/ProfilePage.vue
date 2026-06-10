<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import BadgeGrid from '@/components/BadgeGrid.vue'
import { fetchVirees } from '@/api/virees'
import { useAuthStore } from '@/stores/auth'
import type { Viree } from '@/types/viree'

const auth = useAuthStore()
const router = useRouter()

const initial = computed(() => auth.user?.username?.charAt(0).toUpperCase() ?? '?')

const virees = ref<Viree[]>([])
const vireesLoaded = ref(false)

function vireeDate(viree: Viree): string {
  return new Date(viree.started_at).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
  })
}

function vireeSummary(viree: Viree): string {
  const venues = `${viree.stats.venues} ${viree.stats.venues > 1 ? 'lieux' : 'lieu'}`
  const meters = viree.stats.distance_m
  if (meters === null) return venues
  const km = (meters / 1000).toLocaleString('fr-FR', { maximumFractionDigits: 1 })
  return `${venues} · ${km} km`
}

onMounted(async () => {
  if (auth.user === null) {
    try {
      await auth.loadMe()
    } catch {
      // Token invalide ou expiré : on laisse la session en l'état, l'affichage retombe sur les valeurs vides.
    }
  }

  try {
    virees.value = await fetchVirees()
    vireesLoaded.value = true
  } catch {
    // Historique indisponible : la section reste masquée.
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
          <p class="font-mono text-[10px] uppercase tracking-[0.3em] text-text-3">
            Mon profil
          </p>
          <h1 class="font-serif text-3xl italic text-text">
            {{ auth.user?.username ?? 'Noctambule' }}
          </h1>
        </div>
      </div>

      <dl class="relative mt-8 flex flex-col gap-4">
        <div
          class="flex flex-col gap-1 rounded-2xl border border-hairline bg-glass px-4 py-3"
        >
          <dt class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">
            Pseudo
          </dt>
          <dd data-testid="profile-username" class="text-text">
            {{ auth.user?.username }}
          </dd>
        </div>
        <div
          class="flex flex-col gap-1 rounded-2xl border border-hairline bg-glass px-4 py-3"
        >
          <dt class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">
            Email
          </dt>
          <dd data-testid="profile-email" class="text-text">{{ auth.user?.email }}</dd>
        </div>
      </dl>

      <!-- Gamification : badges débloqués au fil des soirées et des avis -->
      <div class="relative mt-8">
        <BadgeGrid />
      </div>

      <!-- Historique des virées bouclées (façon activités Strava) -->
      <section v-if="vireesLoaded" class="relative mt-8 flex flex-col gap-2">
        <h2 class="font-mono text-[10px] uppercase tracking-[0.3em] text-text-3">
          Mes virées
        </h2>
        <p
          v-if="virees.length === 0"
          data-testid="profile-virees-empty"
          class="rounded-2xl border border-hairline bg-glass px-4 py-4 text-center font-mono text-[10px] uppercase tracking-[0.16em] text-text-3"
        >
          Aucune virée bouclée — pointe-toi quelque part ce soir.
        </p>
        <RouterLink
          v-for="viree in virees"
          :key="viree.public_id"
          :to="`/viree/${viree.public_id}`"
          data-testid="profile-viree"
          class="flex items-center justify-between gap-3 rounded-2xl border border-hairline bg-glass px-4 py-3 transition hover:border-hairline-bright"
        >
          <span class="font-serif italic text-text">{{ vireeDate(viree) }}</span>
          <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-2">
            {{ vireeSummary(viree) }}
          </span>
        </RouterLink>
      </section>

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
