<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { searchUsers } from '@/api/users'
import FollowButton from './FollowButton.vue'
import type { SearchResult } from '@/types/social'

const query = ref('')
const results = ref<SearchResult[]>([])
const isOpen = ref(false)
const root = ref<HTMLElement | null>(null)
let debounceTimer: number | undefined

function onInput(): void {
  window.clearTimeout(debounceTimer)
  if (query.value.trim().length < 2) {
    results.value = []
    isOpen.value = false
    return
  }
  // Débounce : on ne tape l'API qu'une fois la frappe stabilisée.
  debounceTimer = window.setTimeout(async () => {
    try {
      results.value = await searchUsers(query.value.trim())
      isOpen.value = true
    } catch {
      results.value = []
      isOpen.value = false
    }
  }, 300)
}

function close(): void {
  isOpen.value = false
}

// Même mécanique de fermeture que UserMenu : clic en dehors et Échap.
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
  window.clearTimeout(debounceTimer)
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onDocumentKeydown)
})
</script>

<template>
  <div ref="root" class="relative">
    <input
      v-model="query"
      type="search"
      data-testid="user-search-input"
      placeholder="Rechercher un noctambule…"
      class="w-full rounded-full border border-hairline bg-ink-2/60 px-4 py-2.5 text-sm text-text outline-none transition placeholder:text-text-3 focus:border-pink/50"
      @input="onInput"
      @focus="results.length > 0 && (isOpen = true)"
    />

    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="-translate-y-1 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <ul
        v-if="isOpen"
        data-testid="user-search-results"
        class="glass-strong absolute left-0 right-0 top-full z-50 mt-2 flex flex-col rounded-2xl border border-hairline bg-glass-strong p-2 shadow-xl shadow-black/30"
      >
        <li
          v-if="results.length === 0"
          class="px-3 py-2 font-mono text-[10px] uppercase tracking-[0.16em] text-text-3"
        >
          Personne à ce pseudo.
        </li>
        <li
          v-for="user in results"
          :key="user.id"
          data-testid="user-search-result"
          class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 transition hover:bg-glass"
        >
          <RouterLink
            :to="`/u/${user.username}`"
            class="flex min-w-0 flex-col"
            @click="close"
          >
            <span class="truncate font-serif italic text-text">{{ user.username }}</span>
            <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">
              {{ user.followers_count }}
              {{ user.followers_count > 1 ? 'abonnés' : 'abonné' }}
            </span>
          </RouterLink>
          <FollowButton :username="user.username" :is-following="user.is_following" />
        </li>
      </ul>
    </Transition>
  </div>
</template>
