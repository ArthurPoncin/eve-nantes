import { describe, expect, it } from 'vitest'
import { useTheme } from './useTheme'

describe('useTheme', () => {
  it('starts in night mode by default', () => {
    const { theme } = useTheme()
    expect(theme.value).toBe('night')
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

  it('ignores invalid persisted values and falls back to night', () => {
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
