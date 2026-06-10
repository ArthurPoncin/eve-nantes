<script setup lang="ts">
import { ref, watch } from 'vue'
import { followUser, unfollowUser } from '@/api/users'

const props = defineProps<{
  username: string
  isFollowing: boolean
}>()
const emit = defineEmits<{ (e: 'change', isFollowing: boolean): void }>()

const following = ref(props.isFollowing)
const isHovering = ref(false)
const isSaving = ref(false)

watch(
  () => props.isFollowing,
  (value) => {
    following.value = value
  },
)

async function toggle(): Promise<void> {
  if (isSaving.value) return
  isSaving.value = true
  // Optimiste : on bascule tout de suite, on revient en arrière si l'API refuse.
  const before = following.value
  following.value = !before
  try {
    if (following.value) {
      await followUser(props.username)
    } else {
      await unfollowUser(props.username)
    }
    emit('change', following.value)
  } catch {
    following.value = before
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <button
    type="button"
    data-testid="follow-button"
    class="rounded-full px-4 py-1.5 font-mono text-[10px] uppercase tracking-[0.18em] transition"
    :class="
      following
        ? 'border border-cyan/50 text-cyan hover:border-pink/50 hover:text-pink'
        : 'glow-pink bg-pink text-white hover:bg-pink-bright'
    "
    :disabled="isSaving"
    @click.stop="toggle"
    @mouseenter="isHovering = true"
    @mouseleave="isHovering = false"
  >
    {{ following ? (isHovering ? 'Se désabonner' : 'Suivi ✓') : 'Suivre' }}
  </button>
</template>
