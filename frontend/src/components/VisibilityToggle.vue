<script setup lang="ts">
import { ref } from 'vue'
import { apiClient } from '@/api/client'

const props = defineProps<{
  publicId: string
  isPublic: boolean
}>()
const emit = defineEmits<{ (e: 'change', isPublic: boolean): void }>()

const isPublicLocal = ref(props.isPublic)
const isSaving = ref(false)

async function setVisibility(value: boolean): Promise<void> {
  if (isSaving.value || value === isPublicLocal.value) return
  isSaving.value = true
  const before = isPublicLocal.value
  isPublicLocal.value = value
  try {
    await apiClient.patch(`/api/v1/virees/${props.publicId}/visibility`, {
      is_public: value,
    })
    emit('change', value)
  } catch {
    isPublicLocal.value = before
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div
    data-testid="visibility-toggle"
    class="glass flex items-center justify-between gap-3 rounded-2xl border border-hairline bg-glass px-5 py-3"
  >
    <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-text-3">
      Visibilité
    </span>
    <span class="flex rounded-full border border-hairline p-0.5">
      <button
        type="button"
        data-testid="visibility-public"
        class="rounded-full px-3 py-1 font-mono text-[10px] uppercase tracking-[0.16em] transition"
        :class="isPublicLocal ? 'bg-cyan text-ink' : 'text-text-3 hover:text-text'"
        :disabled="isSaving"
        @click="setVisibility(true)"
      >
        Publique
      </button>
      <button
        type="button"
        data-testid="visibility-private"
        class="rounded-full px-3 py-1 font-mono text-[10px] uppercase tracking-[0.16em] transition"
        :class="!isPublicLocal ? 'bg-pink text-white' : 'text-text-3 hover:text-text'"
        :disabled="isSaving"
        @click="setVisibility(false)"
      >
        Abonnés
      </button>
    </span>
  </div>
</template>
