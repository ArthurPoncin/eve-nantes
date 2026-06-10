<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { fetchFollowers, fetchFollowing } from '@/api/users'
import type { UserSummary } from '@/types/social'

const props = defineProps<{
  username: string
  /** Onglet affiché : 'followers' ou 'following'. */
  tab: 'followers' | 'following'
}>()

const users = ref<UserSummary[] | null>(null)
const isLoading = ref(true)

async function load(): Promise<void> {
  isLoading.value = true
  try {
    users.value =
      props.tab === 'followers'
        ? await fetchFollowers(props.username)
        : await fetchFollowing(props.username)
  } catch {
    users.value = null
  } finally {
    isLoading.value = false
  }
}

watch(() => [props.username, props.tab], load, { immediate: true })
</script>

<template>
  <div
    v-if="isLoading"
    data-testid="follow-list-skeleton"
    class="h-16 animate-pulse rounded-2xl border border-hairline bg-glass"
    aria-hidden="true"
  />
  <ul v-else-if="users" data-testid="follow-list" class="flex flex-col gap-1.5">
    <li
      v-if="users.length === 0"
      class="rounded-2xl border border-hairline bg-glass px-4 py-3 text-center font-mono text-[10px] uppercase tracking-[0.16em] text-text-3"
    >
      {{ tab === 'followers' ? 'Aucun abonné pour le moment.' : 'Ne suit personne.' }}
    </li>
    <li v-for="user in users" :key="user.id">
      <RouterLink
        :to="`/u/${user.username}`"
        data-testid="follow-list-user"
        class="block rounded-2xl border border-hairline bg-glass px-4 py-2.5 font-serif italic text-text transition hover:border-hairline-bright"
      >
        {{ user.username }}
      </RouterLink>
    </li>
  </ul>
</template>
