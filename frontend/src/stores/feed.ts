import { ref } from 'vue'
import { defineStore } from 'pinia'
import { fetchFeed } from '@/api/feed'
import { giveKudos, removeKudos } from '@/api/kudos'
import type { FeedItem } from '@/types/social'

/** Le fil des virées des noctambules suivis, paginé par curseur. */
export const useFeedStore = defineStore('feed', () => {
  const items = ref<FeedItem[]>([])
  const nextCursor = ref<string | null>(null)
  const loading = ref(false)
  const loaded = ref(false)

  async function load(): Promise<void> {
    loading.value = true
    try {
      const page = await fetchFeed()
      items.value = page.items
      nextCursor.value = page.nextCursor
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  async function loadMore(): Promise<void> {
    if (!nextCursor.value || loading.value) return
    loading.value = true
    try {
      const page = await fetchFeed(nextCursor.value)
      items.value = [...items.value, ...page.items]
      nextCursor.value = page.nextCursor
    } finally {
      loading.value = false
    }
  }

  /** Bascule « Santé ! » en optimiste, avec retour arrière si l'API refuse. */
  async function toggleKudos(item: FeedItem): Promise<void> {
    const before = { has_kudoed: item.has_kudoed, kudos_count: item.kudos_count }
    item.has_kudoed = !before.has_kudoed
    item.kudos_count = before.kudos_count + (item.has_kudoed ? 1 : -1)

    try {
      if (item.has_kudoed) {
        await giveKudos(item.public_id)
      } else {
        await removeKudos(item.public_id)
      }
    } catch {
      item.has_kudoed = before.has_kudoed
      item.kudos_count = before.kudos_count
    }
  }

  function reset(): void {
    items.value = []
    nextCursor.value = null
    loaded.value = false
  }

  return { items, nextCursor, loading, loaded, load, loadMore, toggleKudos, reset }
})
