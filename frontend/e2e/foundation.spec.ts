import { expect, test } from '@playwright/test'

test('landing page boots with NOCTAMBULE branding and night theme', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveTitle(/NOCTAMBULE/)
  await expect(page.locator('html')).toHaveAttribute('data-theme', /night|sunset/)
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
})
