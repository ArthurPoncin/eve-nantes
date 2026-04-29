import { ref, watch, type Ref } from 'vue'

export type Theme = 'night' | 'sunset'

const STORAGE_KEY = 'noctambule.theme'

function isTheme(value: unknown): value is Theme {
  return value === 'night' || value === 'sunset'
}

function readPersistedTheme(): Theme {
  const stored = localStorage.getItem(STORAGE_KEY)
  return isTheme(stored) ? stored : 'night'
}

interface ThemeState {
  theme: Ref<Theme>
  toggle: () => void
}

let state: ThemeState | null = null

function createState(): ThemeState {
  const theme = ref<Theme>(readPersistedTheme())

  watch(
    theme,
    (next) => {
      localStorage.setItem(STORAGE_KEY, next)
      document.documentElement.setAttribute('data-theme', next)
    },
    { flush: 'sync', immediate: true },
  )

  function toggle(): void {
    theme.value = theme.value === 'night' ? 'sunset' : 'night'
  }

  return { theme, toggle }
}

export function useTheme(): ThemeState {
  if (state === null) {
    state = createState()
  }
  return state
}

export function __resetThemeForTests(): void {
  state = null
}
