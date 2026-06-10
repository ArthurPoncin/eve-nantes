<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchChallenges } from '@/api/challenges'
import type { Challenge } from '@/types/challenge'

const challenges = ref<Challenge[] | null>(null)
const isLoading = ref(true)

onMounted(async () => {
  try {
    challenges.value = await fetchChallenges()
  } catch {
    // Défis indisponibles (session expirée…) : on masque la liste.
    challenges.value = null
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div
    v-if="isLoading"
    data-testid="challenge-skeleton"
    class="h-24 animate-pulse rounded-2xl border border-hairline bg-glass"
    aria-hidden="true"
  />

  <section
    v-else-if="challenges && challenges.length > 0"
    data-testid="challenge-list"
    class="flex flex-col gap-3"
  >
    <h2 class="font-mono text-[10px] uppercase tracking-[0.3em] text-text-3">
      Défis du mois
    </h2>

    <ul class="flex flex-col gap-3">
      <li
        v-for="challenge in challenges"
        :key="challenge.id"
        data-testid="challenge-item"
        class="flex flex-col gap-2 rounded-2xl border px-4 py-3"
        :class="
          challenge.completed
            ? 'glow-gold border-gold/40 bg-glass'
            : 'border-hairline bg-glass'
        "
      >
        <div class="flex items-center justify-between gap-3">
          <span class="flex items-center gap-2">
            <span
              class="text-xl"
              :class="challenge.completed ? 'text-gold' : 'text-text-3'"
              aria-hidden="true"
            >
              {{ challenge.icon }}
            </span>
            <span
              class="font-serif italic"
              :class="challenge.completed ? 'text-text' : 'text-text-2'"
            >
              {{ challenge.label }}
            </span>
          </span>
          <span
            data-testid="challenge-progress"
            class="font-mono text-[10px] uppercase tracking-[0.18em]"
            :class="challenge.completed ? 'text-gold' : 'text-text-3'"
          >
            {{
              challenge.completed ? 'Bouclé ✓' : `${challenge.progress}/${challenge.goal}`
            }}
          </span>
        </div>
        <span class="text-xs text-text-3">{{ challenge.description }}</span>
        <span class="h-1.5 overflow-hidden rounded-full bg-white/5">
          <span
            class="block h-full rounded-full transition-all"
            :class="challenge.completed ? 'bg-gold' : 'bg-violet-bright'"
            :style="{ width: `${(challenge.progress / challenge.goal) * 100}%` }"
            aria-hidden="true"
          />
        </span>
      </li>
    </ul>
  </section>
</template>
