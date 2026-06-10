<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchVenueReviews, postVenueReview } from '@/api/reviews'
import { useAuthStore } from '@/stores/auth'
import type { VenueReviews } from '@/types/review'

const props = defineProps<{ slug: string }>()

const auth = useAuthStore()

const data = ref<VenueReviews | null>(null)
const isLoading = ref(true)

// Formulaire : note choisie (0 = rien) + commentaire libre.
const rating = ref(0)
const comment = ref('')
const isSubmitting = ref(false)
const submitError = ref(false)

async function load(): Promise<void> {
  try {
    data.value = await fetchVenueReviews(props.slug)
  } catch {
    // Avis indisponibles : la fiche reste lisible, on masque la section.
    data.value = null
  } finally {
    isLoading.value = false
  }
}

async function submit(): Promise<void> {
  if (rating.value === 0 || isSubmitting.value) return
  isSubmitting.value = true
  submitError.value = false
  try {
    await postVenueReview(props.slug, {
      rating: rating.value,
      ...(comment.value.trim() !== '' ? { comment: comment.value.trim() } : {}),
    })
    rating.value = 0
    comment.value = ''
    // On recharge la liste : la moyenne est recalculée côté serveur.
    await load()
  } catch {
    submitError.value = true
  } finally {
    isSubmitting.value = false
  }
}

function formatAverage(value: number): string {
  return value.toLocaleString('fr-FR', { maximumFractionDigits: 1 })
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })
}

function stars(value: number): string {
  return '★'.repeat(value) + '☆'.repeat(5 - value)
}

onMounted(load)
</script>

<template>
  <div
    v-if="isLoading"
    data-testid="reviews-skeleton"
    class="glass h-24 animate-pulse rounded-2xl border border-hairline bg-glass"
    aria-hidden="true"
  />

  <section v-else-if="data" data-testid="reviews-section" class="flex flex-col gap-3">
    <div class="flex items-baseline justify-between">
      <h2 class="font-serif text-2xl italic text-text">Avis</h2>
      <span
        v-if="data.average !== null"
        data-testid="reviews-average"
        class="font-mono text-[11px] uppercase tracking-[0.18em] text-gold"
      >
        ★ {{ formatAverage(data.average) }} · {{ data.count }} avis
      </span>
    </div>

    <!-- Formulaire (connecté) ou invitation à se connecter -->
    <form
      v-if="auth.isAuthenticated"
      data-testid="review-form"
      class="glass flex flex-col gap-3 rounded-2xl border border-hairline bg-glass p-4"
      @submit.prevent="submit"
    >
      <div class="flex items-center gap-1" role="radiogroup" aria-label="Ta note sur 5">
        <button
          v-for="star in 5"
          :key="star"
          type="button"
          :data-testid="`review-star-${star}`"
          role="radio"
          :aria-checked="rating === star"
          :aria-label="`${star} étoile${star > 1 ? 's' : ''}`"
          class="text-2xl transition"
          :class="star <= rating ? 'text-gold' : 'text-text-3 hover:text-text-2'"
          @click="rating = star"
        >
          ★
        </button>
      </div>
      <textarea
        v-model="comment"
        data-testid="review-comment"
        rows="2"
        maxlength="500"
        placeholder="Raconte ta soirée (optionnel)…"
        class="w-full resize-none rounded-xl border border-hairline bg-transparent px-3 py-2 text-sm text-text placeholder:text-text-3 focus:border-hairline-bright focus:outline-none"
      />
      <p v-if="submitError" data-testid="review-error" class="text-xs text-pink">
        L'avis n'a pas pu être envoyé. Réessaie.
      </p>
      <button
        type="submit"
        data-testid="review-submit"
        :disabled="rating === 0 || isSubmitting"
        class="self-end rounded-full border border-gold/50 px-5 py-2 font-mono text-[10px] uppercase tracking-[0.18em] text-gold transition enabled:hover:bg-gold/10 disabled:opacity-40"
      >
        {{ isSubmitting ? 'Envoi…' : 'Publier mon avis' }}
      </button>
    </form>
    <RouterLink
      v-else
      :to="`/login?redirect=/venues/${slug}`"
      data-testid="reviews-login-cta"
      class="glass rounded-2xl border border-hairline bg-glass px-5 py-4 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-2 transition hover:border-gold/40 hover:text-gold"
    >
      Se connecter pour donner son avis
    </RouterLink>

    <!-- Liste des avis -->
    <p
      v-if="data.reviews.length === 0"
      data-testid="reviews-empty"
      class="rounded-2xl border border-hairline bg-glass px-5 py-6 text-center font-mono text-[11px] uppercase tracking-[0.18em] text-text-3"
    >
      Aucun avis pour l'instant. Lance-toi !
    </p>
    <ul v-else class="flex flex-col gap-3">
      <li
        v-for="review in data.reviews"
        :key="review.id"
        data-testid="review-item"
        class="glass rounded-2xl border border-hairline bg-glass p-4"
      >
        <div class="flex items-center justify-between gap-3">
          <span class="font-serif italic text-text">{{ review.username }}</span>
          <span class="font-mono text-xs tracking-[0.2em] text-gold" aria-hidden="true">
            {{ stars(review.rating) }}
          </span>
          <span class="sr-only">{{ review.rating }} sur 5</span>
        </div>
        <p v-if="review.comment" class="mt-2 text-sm text-text-2">
          {{ review.comment }}
        </p>
        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">
          {{ formatDate(review.created_at) }}
        </p>
      </li>
    </ul>
  </section>
</template>
