import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useTheme } from './useTheme'

// Le défaut suit l'heure locale : on fige l'horloge pour des tests stables.
function setClock(hours: number, minutes = 0): void {
  vi.setSystemTime(new Date(2026, 5, 10, hours, minutes))
}

beforeEach(() => {
  vi.useFakeTimers()
  setClock(22)
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useTheme', () => {
  it('démarre en night le soir (22h)', () => {
    const { theme } = useTheme()
    expect(theme.value).toBe('night')
  })

  it('démarre en sunset en journée (15h)', () => {
    setClock(15)
    const { theme } = useTheme()
    expect(theme.value).toBe('sunset')
  })

  it('bascule en night à 21h pile et en sunset à 8h pile', () => {
    setClock(21)
    expect(useTheme().theme.value).toBe('night')
  })

  it('reste en night juste avant 8h', () => {
    setClock(7, 59)
    expect(useTheme().theme.value).toBe('night')
  })

  it('passe en sunset à 8h', () => {
    setClock(8)
    expect(useTheme().theme.value).toBe('sunset')
  })

  it("n'écrit pas le défaut horaire en localStorage", () => {
    useTheme()
    expect(localStorage.getItem('noctambule.theme')).toBeNull()
  })

  it('toggles between night and sunset', () => {
    const { theme, toggle } = useTheme()
    expect(theme.value).toBe('night')
    toggle()
    expect(theme.value).toBe('sunset')
    toggle()
    expect(theme.value).toBe('night')
  })

  it('persists the current theme to localStorage', () => {
    const { toggle } = useTheme()
    toggle()
    expect(localStorage.getItem('noctambule.theme')).toBe('sunset')
    toggle()
    expect(localStorage.getItem('noctambule.theme')).toBe('night')
  })

  it('restores a previously persisted theme on init', () => {
    localStorage.setItem('noctambule.theme', 'sunset')
    const { theme } = useTheme()
    expect(theme.value).toBe('sunset')
  })

  it("le choix manuel persisté garde la priorité sur l'heure", () => {
    setClock(23)
    localStorage.setItem('noctambule.theme', 'sunset')
    const { theme } = useTheme()
    expect(theme.value).toBe('sunset')
  })

  it('ignores invalid persisted values and falls back to the clock', () => {
    localStorage.setItem('noctambule.theme', 'noon')
    const { theme } = useTheme()
    expect(theme.value).toBe('night')
  })

  it('applies the current theme to the html data-theme attribute on init', () => {
    localStorage.setItem('noctambule.theme', 'sunset')
    useTheme()
    expect(document.documentElement.getAttribute('data-theme')).toBe('sunset')
  })

  it('updates the html data-theme attribute when the theme changes', () => {
    const { toggle } = useTheme()
    expect(document.documentElement.getAttribute('data-theme')).toBe('night')
    toggle()
    expect(document.documentElement.getAttribute('data-theme')).toBe('sunset')
  })

  it('shares the theme state across multiple consumers', () => {
    const writer = useTheme()
    const reader = useTheme()
    writer.toggle()
    expect(reader.theme.value).toBe('sunset')
  })
})
