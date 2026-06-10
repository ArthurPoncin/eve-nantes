<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useFeedStore } from '@/stores/feed'
import FeedCard from '@/components/FeedCard.vue'
import UserSearch from '@/components/UserSearch.vue'

const feed = useFeedStore()
const hasError = ref(false)

onMounted(async () => {
  try {
    await feed.load()
  } catch {
    hasError.value = true
  }
})
</script>

<template>
  <main class="mx-auto flex w-full max-w-2xl flex-col gap-5 px-4 pb-24 pt-6 sm:px-6">
    <!-- En-tête + recherche de noctambules -->
    <section class="flex flex-col gap-4">
      <div class="flex items-baseline justify-between">
        <h1 data-testid="feed-title" class="font-serif text-4xl italic text-text">
          Mon fil
        </h1>
        <span class="font-mono text-[10px] uppercase tracking-[0.22em] text-text-3">
          Les virées de ta bande
        </span>
      </div>
      <UserSearch />
    </section>

    <p
      v-if="feed.loading && !feed.loaded"
      data-testid="feed-loading"
      class="py-16 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Chargement du fil…
    </p>
    <p
      v-else-if="hasError"
      data-testid="feed-error"
      class="py-16 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Fil indisponible.
    </p>

    <template v-else-if="feed.loaded">
      <p
        v-if="feed.items.length === 0"
        data-testid="feed-empty"
        class="rounded-2xl border border-hairline bg-glass px-5 py-10 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
      >
        Suis des noctambules pour remplir ton fil — ou boucle ta première virée.
      </p>

      <FeedCard
        v-for="item in feed.items"
        :key="item.public_id"
        :item="item"
        @kudos="feed.toggleKudos(item)"
      />

      <button
        v-if="feed.nextCursor"
        type="button"
        data-testid="feed-more"
        class="w-full rounded-full border border-hairline px-6 py-3 font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:border-hairline-bright hover:text-text"
        :disabled="feed.loading"
        @click="feed.loadMore()"
      >
        Voir plus
      </button>
    </template>
  </main>
</template>
