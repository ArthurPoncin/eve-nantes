import { beforeEach } from 'vitest'
import { __resetThemeForTests } from '@/composables/useTheme'

beforeEach(() => {
  localStorage.clear()
  document.documentElement.removeAttribute('data-theme')
  __resetThemeForTests()
})
