import { expect, test } from '@playwright/test'

test('shows weather data on landing when the API responds', async ({ page }) => {
  await page.route('**/api/v1/weather', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        temp: 14.7,
        feels_like: 12.3,
        condition: 'nuit étoilée',
        icon: '01n',
        wind: 2.1,
        humidity: 70,
      }),
    })
  })

  await page.goto('/')
  const widget = page.getByTestId('weather-widget')
  await expect(widget).toBeVisible()
  await expect(page.getByTestId('weather-temp')).toHaveText('15°C')
  await expect(page.getByTestId('weather-condition')).toHaveText('nuit étoilée')
  await expect(widget.locator('img')).toBeVisible()
})

test('falls back to "Météo indisponible" on 500', async ({ page }) => {
  await page.route('**/api/v1/weather', async (route) => {
    await route.fulfill({
      status: 500,
      contentType: 'application/json',
      body: JSON.stringify({ error: 'boom' }),
    })
  })

  await page.goto('/')
  await expect(page.getByTestId('weather-error')).toHaveText('Météo indisponible')
})

test('shows a loading skeleton before the response arrives', async ({ page }) => {
  await page.route('**/api/v1/weather', async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 1000))
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        temp: 10,
        feels_like: 8,
        condition: 'pluie fine',
        icon: '10n',
        wind: 4,
        humidity: 85,
      }),
    })
  })

  await page.goto('/')
  await expect(page.getByTestId('weather-skeleton')).toBeVisible()
  await expect(page.getByTestId('weather-temp')).toBeVisible()
  await expect(page.getByTestId('weather-skeleton')).toBeHidden()
})
