<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { fetchBadges } from '@/api/badges'
import { useAuthStore } from '@/stores/auth'
import type { Badge } from '@/types/badge'

const auth = useAuthStore()
const router = useRouter()

const isOpen = ref(false)
const root = ref<HTMLElement | null>(null)

const initial = computed(() => auth.user?.username?.charAt(0).toUpperCase() ?? '?')

// Chargés paresseusement à la première ouverture : pas d'appel API tant que
// le menu reste fermé. En cas d'erreur la ligne badges est simplement masquée.
const badges = ref<Badge[] | null>(null)
const badgesRequested = ref(false)

const unlockedCount = computed(
  () => badges.value?.filter((badge) => badge.unlocked).length ?? 0,
)

function toggle(): void {
  isOpen.value = !isOpen.value
  if (isOpen.value && !badgesRequested.value) {
    badgesRequested.value = true
    void loadBadges()
  }
}

async function loadBadges(): Promise<void> {
  try {
    badges.value = await fetchBadges()
  } catch {
    badges.value = null
  }
}

function close(): void {
  isOpen.value = false
}

// Fermeture au clic en dehors et à Échap : écouteurs globaux, retirés au démontage.
function onDocumentClick(event: MouseEvent): void {
  if (root.value && !root.value.contains(event.target as Node)) {
    close()
  }
}

function onDocumentKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onDocumentKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onDocumentKeydown)
})

async function onLogout(): Promise<void> {
  close()
  await auth.logout()
  await router.push('/')
}
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      data-testid="user-menu-button"
      aria-label="Menu du compte"
      aria-haspopup="menu"
      :aria-expanded="isOpen"
      class="glow-violet flex h-9 w-9 items-center justify-center rounded-full bg-violet-bright font-serif text-base italic text-white transition hover:brightness-110"
      :class="isOpen ? 'ring-2 ring-violet/60 ring-offset-2 ring-offset-transparent' : ''"
      @click="toggle"
    >
      {{ initial }}
    </button>

    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="-translate-y-1 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        data-testid="user-menu"
        role="menu"
        class="glass-strong absolute right-0 top-full z-50 mt-3 w-60 rounded-2xl border border-hairline bg-glass-strong p-2 shadow-xl shadow-black/30"
      >
        <div class="px-3 pb-2 pt-1.5">
          <p class="truncate font-serif text-lg italic text-text">
            {{ auth.user?.username ?? 'Noctambule' }}
          </p>
          <p class="truncate text-xs text-text-3">{{ auth.user?.email }}</p>
          <p
            v-if="badges"
            data-testid="user-menu-badges"
            class="mt-1.5 font-mono text-[10px] uppercase tracking-[0.18em] text-gold"
          >
            ◆ {{ unlockedCount }}/{{ badges.length }} badges
          </p>
        </div>

        <div class="mx-1 border-t border-hairline" aria-hidden="true" />

        <nav class="flex flex-col py-1" aria-label="Compte">
          <RouterLink
            to="/profil"
            role="menuitem"
            data-testid="user-menu-profile"
            class="rounded-xl px-3 py-2 font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:bg-glass hover:text-text"
            @click="close"
          >
            Profil
          </RouterLink>
          <RouterLink
            to="/favoris"
            role="menuitem"
            data-testid="user-menu-favorites"
            class="rounded-xl px-3 py-2 font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:bg-glass hover:text-text"
            @click="close"
          >
            Favoris
          </RouterLink>
        </nav>

        <div class="mx-1 border-t border-hairline" aria-hidden="true" />

        <button
          type="button"
          role="menuitem"
          data-testid="user-menu-logout"
          class="mt-1 w-full rounded-xl px-3 py-2 text-left font-mono text-[11px] uppercase tracking-[0.18em] text-text-3 transition hover:bg-glass hover:text-pink"
          @click="onLogout"
        >
          Déconnexion
        </button>
      </div>
    </Transition>
  </div>
</template>
