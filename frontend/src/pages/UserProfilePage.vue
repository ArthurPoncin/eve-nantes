<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { fetchProfile } from '@/api/users'
import { useAuthStore } from '@/stores/auth'
import FollowButton from '@/components/FollowButton.vue'
import FollowList from '@/components/FollowList.vue'
import StatCard from '@/components/StatCard.vue'
import type { PublicProfile } from '@/types/social'
import type { Viree } from '@/types/viree'

const route = useRoute()
const auth = useAuthStore()

const profile = ref<PublicProfile | null>(null)
const isLoading = ref(true)
const hasError = ref(false)
const openList = ref<'followers' | 'following' | null>(null)

const username = computed(() => {
  const param = route.params.username
  return (Array.isArray(param) ? param[0] : param) ?? ''
})

const isMe = computed(() => auth.user?.username === profile.value?.username)

const initial = computed(() => profile.value?.username.charAt(0).toUpperCase() ?? '?')

const kmLabel = computed(() =>
  (profile.value?.stats.total_km ?? 0).toLocaleString('fr-FR', {
    maximumFractionDigits: 1,
  }),
)

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
  return `${venues} · ${(meters / 1000).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} km`
}

function toggleList(tab: 'followers' | 'following'): void {
  openList.value = openList.value === tab ? null : tab
}

function onFollowChange(isFollowing: boolean): void {
  if (!profile.value) return
  profile.value.is_following = isFollowing
  profile.value.followers_count += isFollowing ? 1 : -1
}

async function load(): Promise<void> {
  isLoading.value = true
  hasError.value = false
  openList.value = null
  try {
    profile.value = await fetchProfile(username.value)
  } catch {
    hasError.value = true
    profile.value = null
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
// Navigation /u/a → /u/b : le composant est réutilisé, on recharge.
watch(username, load)
</script>

<template>
  <main class="mx-auto flex w-full max-w-2xl flex-col gap-5 px-4 pb-24 pt-6 sm:px-6">
    <p
      v-if="isLoading"
      data-testid="user-profile-loading"
      class="py-20 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Chargement du profil…
    </p>
    <p
      v-else-if="hasError || profile === null"
      data-testid="user-profile-error"
      class="py-20 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Noctambule introuvable.
    </p>

    <template v-else>
      <!-- Hero -->
      <section
        class="glass-strong relative overflow-hidden rounded-3xl border border-hairline bg-glass-strong p-6 sm:p-8"
      >
        <div
          aria-hidden="true"
          class="pointer-events-none absolute -left-16 -top-20 h-48 w-48 rounded-full bg-violet opacity-25 blur-3xl"
        />
        <div class="relative flex items-center justify-between gap-4">
          <div class="flex items-center gap-4">
            <span
              class="glow-violet flex h-14 w-14 items-center justify-center rounded-full bg-violet-bright font-serif text-2xl italic text-white"
              aria-hidden="true"
            >
              {{ initial }}
            </span>
            <div class="flex flex-col">
              <h1
                data-testid="user-profile-name"
                class="font-serif text-3xl italic text-text"
              >
                {{ profile.username }}
              </h1>
              <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-gold">
                ◆ {{ profile.badge_count }}
                {{ profile.badge_count > 1 ? 'badges' : 'badge' }}
              </p>
            </div>
          </div>
          <FollowButton
            v-if="auth.isAuthenticated && !isMe && profile.is_following !== null"
            :username="profile.username"
            :is-following="profile.is_following"
            @change="onFollowChange"
          />
          <RouterLink
            v-else-if="!auth.isAuthenticated"
            :to="`/login?redirect=/u/${profile.username}`"
            data-testid="user-profile-login"
            class="shrink-0 rounded-full border border-pink/50 px-4 py-1.5 font-mono text-[10px] uppercase tracking-[0.18em] text-pink transition hover:bg-pink/10"
          >
            Se connecter pour suivre
          </RouterLink>
        </div>

        <!-- Abonnés / abonnements -->
        <div class="relative mt-5 flex gap-2">
          <button
            type="button"
            data-testid="user-profile-followers"
            class="rounded-full border border-hairline px-3 py-1 font-mono text-[10px] uppercase tracking-[0.16em] text-text-2 transition hover:border-hairline-bright"
            :class="openList === 'followers' ? 'border-pink/50 text-pink' : ''"
            @click="toggleList('followers')"
          >
            Abonnés {{ profile.followers_count }}
          </button>
          <button
            type="button"
            data-testid="user-profile-following"
            class="rounded-full border border-hairline px-3 py-1 font-mono text-[10px] uppercase tracking-[0.16em] text-text-2 transition hover:border-hairline-bright"
            :class="openList === 'following' ? 'border-pink/50 text-pink' : ''"
            @click="toggleList('following')"
          >
            Abonnements {{ profile.following_count }}
          </button>
        </div>
      </section>

      <FollowList v-if="openList" :username="profile.username" :tab="openList" />

      <!-- Stats publiques -->
      <section class="grid grid-cols-3 gap-3" data-testid="user-profile-stats">
        <StatCard label="Virées" :value="String(profile.stats.virees_count)" />
        <StatCard label="Km de nuit" :value="kmLabel" />
        <StatCard label="Lieux" :value="String(profile.stats.distinct_venues)" />
      </section>

      <!-- Virées récentes -->
      <section class="flex flex-col gap-2">
        <h2 class="font-mono text-[10px] uppercase tracking-[0.3em] text-text-3">
          Virées récentes
        </h2>
        <p
          v-if="profile.recent_virees.length === 0"
          data-testid="user-profile-virees-empty"
          class="rounded-2xl border border-hairline bg-glass px-4 py-4 text-center font-mono text-[10px] uppercase tracking-[0.16em] text-text-3"
        >
          Rien à montrer pour l'instant.
        </p>
        <RouterLink
          v-for="viree in profile.recent_virees"
          :key="viree.public_id"
          :to="`/viree/${viree.public_id}`"
          data-testid="user-profile-viree"
          class="flex items-center justify-between gap-3 rounded-2xl border border-hairline bg-glass px-4 py-3 transition hover:border-hairline-bright"
        >
          <span class="font-serif italic text-text">{{ vireeDate(viree) }}</span>
          <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-2">
            {{ vireeSummary(viree) }}
          </span>
        </RouterLink>
      </section>
    </template>
  </main>
</template>
