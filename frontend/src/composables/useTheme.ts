import { ref, watch, type Ref } from 'vue'

export type Theme = 'night' | 'sunset'

const STORAGE_KEY = 'noctambule.theme'

function isTheme(value: unknown): value is Theme {
  return value === 'night' || value === 'sunset'
}

// Sans choix explicite, le thème suit l'heure locale : sunset en journée
// (8h–21h), night en soirée et la nuit (21h–8h).
function themeForHour(hour: number): Theme {
  return hour >= 8 && hour < 21 ? 'sunset' : 'night'
}

function readPersistedTheme(): Theme {
  const stored = localStorage.getItem(STORAGE_KEY)
  return isTheme(stored) ? stored : themeForHour(new Date().getHours())
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
      document.documentElement.setAttribute('data-theme', next)
    },
    { flush: 'sync', immediate: true },
  )

  function toggle(): void {
    theme.value = theme.value === 'night' ? 'sunset' : 'night'
    // Seul un choix explicite est persisté : le défaut horaire doit pouvoir
    // changer d'une visite à l'autre.
    localStorage.setItem(STORAGE_KEY, theme.value)
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
