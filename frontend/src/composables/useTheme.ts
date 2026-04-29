import { ref, watch, type Ref } from 'vue'

export type Theme = 'night' | 'sunset'

const STORAGE_KEY = 'noctambule.theme'
const VALID_THEMES: readonly Theme[] = ['night', 'sunset']

function readPersistedTheme(): Theme {
  const stored = localStorage.getItem(STORAGE_KEY)
  return VALID_THEMES.includes(stored as Theme) ? (stored as Theme) : 'night'
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
