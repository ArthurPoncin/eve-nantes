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
</script>

<template>
  <main class="flex min-h-[80vh] items-center justify-center px-6">
    <section
      class="w-full max-w-sm rounded-2xl border border-white/10 bg-white/5 p-8"
      aria-label="Authentification"
    >
      <h1 class="font-serif text-3xl italic text-ink-primary">
        {{ isRegister ? 'Rejoindre la nuit' : 'Bon retour' }}
      </h1>
      <p class="mt-1 text-sm text-ink-muted">
        {{ isRegister ? 'Crée ton compte NOCTAMBULE.' : 'Connecte-toi pour continuer.' }}
      </p>

      <div class="mt-6 flex gap-2 font-mono text-[10px] uppercase tracking-widest">
        <button
          type="button"
          class="rounded-full px-3 py-1 transition"
          :class="
            !isRegister ? 'bg-white/10 text-ink-primary' : 'text-ink-muted hover:text-ink-primary'
          "
          @click="switchMode('login')"
        >
          Connexion
        </button>
        <button
          type="button"
          class="rounded-full px-3 py-1 transition"
          :class="
            isRegister ? 'bg-white/10 text-ink-primary' : 'text-ink-muted hover:text-ink-primary'
          "
          @click="switchMode('register')"
        >
          Inscription
        </button>
      </div>

      <form class="mt-6 flex flex-col gap-4" @submit.prevent="onSubmit">
        <label v-if="isRegister" class="flex flex-col gap-1">
          <span class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">
            Pseudo
          </span>
          <input
            v-model="form.username"
            type="text"
            name="username"
            autocomplete="username"
            required
            class="rounded-lg border border-white/10 bg-surface-night/40 px-3 py-2 text-ink-primary outline-none transition focus:border-white/30"
          />
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">
            Email
          </span>
          <input
            v-model="form.email"
            type="email"
            name="email"
            autocomplete="email"
            required
            class="rounded-lg border border-white/10 bg-surface-night/40 px-3 py-2 text-ink-primary outline-none transition focus:border-white/30"
          />
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">
            Mot de passe
          </span>
          <input
            v-model="form.password"
            type="password"
            name="password"
            :autocomplete="isRegister ? 'new-password' : 'current-password'"
            required
            class="rounded-lg border border-white/10 bg-surface-night/40 px-3 py-2 text-ink-primary outline-none transition focus:border-white/30"
          />
        </label>

        <label v-if="isRegister" class="flex flex-col gap-1">
          <span class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">
            Confirme le mot de passe
          </span>
          <input
            v-model="form.password_confirmation"
            type="password"
            name="password_confirmation"
            autocomplete="new-password"
            required
            class="rounded-lg border border-white/10 bg-surface-night/40 px-3 py-2 text-ink-primary outline-none transition focus:border-white/30"
          />
        </label>

        <p v-if="errorMessage" data-testid="auth-error" class="text-sm text-mood-festif">
          {{ errorMessage }}
        </p>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="mt-2 rounded-full bg-ink-primary px-4 py-2 font-mono text-xs uppercase tracking-widest text-surface-night transition hover:opacity-90 disabled:opacity-50"
        >
          {{ isSubmitting ? 'Patiente…' : isRegister ? 'Créer mon compte' : 'Se connecter' }}
        </button>
      </form>
    </section>
  </main>
</template>
