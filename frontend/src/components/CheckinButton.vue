<script setup lang="ts">
import { computed, ref } from 'vue'
import { useVireeStore } from '@/stores/viree'

const props = defineProps<{ slug: string }>()

const viree = useVireeStore()
const isSaving = ref(false)

// Dernier lieu pointé de la virée en cours : le check-in y est déjà fait.
const isHere = computed(() => viree.lastVenueSlug === props.slug)

async function onCheckIn(): Promise<void> {
  if (isHere.value || isSaving.value) return
  isSaving.value = true
  try {
    await viree.checkIn(props.slug)
  } catch {
    // Check-in refusé (réseau, session expirée…) : le bouton reste réessayable.
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <button
    type="button"
    data-testid="venue-checkin"
    class="flex-1 rounded-full px-6 py-3.5 text-center font-mono text-[11px] uppercase tracking-[0.18em] transition"
    :class="
      isHere
        ? 'border border-cyan/50 text-cyan'
        : 'glow-cyan bg-cyan text-ink hover:opacity-90'
    "
    :disabled="isHere || isSaving"
    @click="onCheckIn"
  >
    {{ isHere ? 'Tu es ici ✓' : 'J’y suis' }}
  </button>
</template>
