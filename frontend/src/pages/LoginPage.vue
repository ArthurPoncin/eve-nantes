<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { isAxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'

type Mode = 'login' | 'register'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const mode = ref<Mode>('login')
const isSubmitting = ref(false)
const errorMessage = ref('')

const form = reactive({
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const isRegister = computed(() => mode.value === 'register')

function switchMode(next: Mode): void {
  mode.value = next
  errorMessage.value = ''
}

function extractError(error: unknown): string {
  if (isAxiosError(error)) {
    const data = error.response?.data as { error?: string; message?: string } | undefined
    return data?.error ?? data?.message ?? 'Une erreur est survenue. Réessaie.'
  }
  return 'Une erreur est survenue. Réessaie.'
}

async function onSubmit(): Promise<void> {
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    if (isRegister.value) {
      await auth.register({
        username: form.username,
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
      })
    } else {
      await auth.login({ email: form.email, password: form.password })
    }
    const { redirect } = route.query
    const target = (Array.isArray(redirect) ? redirect[0] : redirect) ?? '/'
    await router.push(target)
  } catch (error) {
    errorMessage.value = extractError(error)
  } finally {
    isSubmitting.value = false
  }
}

const INPUT_CLASS =
  'rounded-xl border border-hairline bg-ink-2/60 px-3 py-2.5 text-text outline-none transition placeholder:text-text-3 focus:border-pink/50'
</script>

<template>
  <main class="flex min-h-[80vh] items-center justify-center px-6 py-12">
    <section
      class="glass-strong relative w-full max-w-sm overflow-hidden rounded-3xl border border-hairline bg-glass-strong p-8"
      aria-label="Authentification"
    >
      <div
        aria-hidden="true"
        class="pointer-events-none absolute -right-16 -top-20 h-48 w-48 rounded-full bg-pink opacity-25 blur-3xl"
      />

      <p class="relative font-mono text-[10px] uppercase tracking-[0.3em] text-text-3">
        Nantes · Nightlife
      </p>
      <h1 class="relative mt-2 font-serif text-3xl italic text-text">
        {{ isRegister ? 'Rejoindre la nuit' : 'Bon retour' }}
      </h1>
      <p class="relative mt-1 text-sm text-text-2">
        {{ isRegister ? 'Crée ton compte NOCTAMBULE.' : 'Connecte-toi pour continuer.' }}
      </p>

      <div
        class="relative mt-6 flex gap-2 font-mono text-[10px] uppercase tracking-[0.16em]"
      >
        <button
          type="button"
          class="rounded-full px-3 py-1.5 transition"
          :class="!isRegister ? 'bg-pink/15 text-pink' : 'text-text-3 hover:text-text'"
          @click="switchMode('login')"
        >
          Connexion
        </button>
        <button
          type="button"
          class="rounded-full px-3 py-1.5 transition"
          :class="isRegister ? 'bg-pink/15 text-pink' : 'text-text-3 hover:text-text'"
          @click="switchMode('register')"
        >
          Inscription
        </button>
      </div>

      <form class="relative mt-6 flex flex-col gap-4" @submit.prevent="onSubmit">
        <label v-if="isRegister" class="flex flex-col gap-1.5">
          <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3"
            >Pseudo</span
          >
          <input
            v-model="form.username"
            type="text"
            name="username"
            autocomplete="username"
            required
            :class="INPUT_CLASS"
          />
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3"
            >Email</span
          >
          <input
            v-model="form.email"
            type="email"
            name="email"
            autocomplete="email"
            required
            :class="INPUT_CLASS"
          />
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">
            Mot de passe
          </span>
          <input
            v-model="form.password"
            type="password"
            name="password"
            :autocomplete="isRegister ? 'new-password' : 'current-password'"
            required
            :class="INPUT_CLASS"
          />
        </label>

        <label v-if="isRegister" class="flex flex-col gap-1.5">
          <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-text-3">
            Confirme le mot de passe
          </span>
          <input
            v-model="form.password_confirmation"
            type="password"
            name="password_confirmation"
            autocomplete="new-password"
            required
            :class="INPUT_CLASS"
          />
        </label>

        <p v-if="errorMessage" data-testid="auth-error" class="text-sm text-pink">
          {{ errorMessage }}
        </p>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="glow-pink mt-2 rounded-full bg-pink px-4 py-3 font-mono text-[11px] uppercase tracking-[0.18em] text-white transition hover:bg-pink-bright disabled:opacity-50"
        >
          {{
            isSubmitting ? 'Patiente…' : isRegister ? 'Créer mon compte' : 'Se connecter'
          }}
        </button>
      </form>
    </section>
  </main>
</template>
