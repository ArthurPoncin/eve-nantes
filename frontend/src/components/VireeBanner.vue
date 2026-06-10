<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useVireeStore } from '@/stores/viree'

const auth = useAuthStore()
const viree = useVireeStore()
const router = useRouter()
const isClosing = ref(false)

const sinceLabel = computed(() => {
  if (!viree.current) return ''
  return new Date(viree.current.started_at).toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  })
})

const venuesLabel = computed(
  () => `${viree.venuesCount} ${viree.venuesCount > 1 ? 'lieux' : 'lieu'}`,
)

onMounted(() => {
  // La bannière vit dans le shell : c'est elle qui hydrate la virée en cours.
  if (auth.isAuthenticated && !viree.loaded) {
    viree.load().catch(() => {})
  }
})

async function onClose(): Promise<void> {
  if (isClosing.value) return
  isClosing.value = true
  try {
    const recap = await viree.close()
    await router.push(`/viree/${recap.public_id}`)
  } catch {
    // Clôture impossible : la bannière reste affichée, on pourra réessayer.
  } finally {
    isClosing.value = false
  }
}
</script>

<template>
  <div
    v-if="viree.isActive"
    data-testid="viree-banner"
    class="glass relative z-40 flex items-center justify-between gap-3 border-b border-pink/40 bg-glass px-6 py-2.5"
  >
    <p
      class="flex items-center gap-2.5 font-mono text-[11px] uppercase tracking-[0.18em] text-text-2"
    >
      <span class="h-2 w-2 animate-pulse rounded-full bg-pink" aria-hidden="true" />
      <span>
        Virée en cours
        <span class="text-text-3">· {{ venuesLabel }} · depuis {{ sinceLabel }}</span>
      </span>
    </p>
    <button
      type="button"
      data-testid="viree-banner-close"
      class="shrink-0 rounded-full border border-pink/50 px-4 py-1.5 font-mono text-[10px] uppercase tracking-[0.18em] text-pink transition hover:bg-pink/10"
      :disabled="isClosing"
      @click="onClose"
    >
      Terminer
    </button>
  </div>
</template>
