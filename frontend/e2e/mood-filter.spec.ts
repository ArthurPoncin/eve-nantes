import { expect, test, type Route } from '@playwright/test'

interface VenueFixture {
  id: number
  name: string
  slug: string
  address_line: string
  postal_code: string
  city: string
  mood: string | null
  capacity: number | null
  latitude: number | null
  longitude: number | null
}

function venue(overrides: Partial<VenueFixture> & Pick<VenueFixture, 'id' | 'name' | 'slug' | 'mood'>): VenueFixture {
  return {
    address_line: '1 Rue de la Nuit',
    postal_code: '44000',
    city: 'Nantes',
    capacity: null,
    latitude: null,
    longitude: null,
    ...overrides,
  }
}

const FERRAILLEUR = venue({ id: 1, name: 'Le Ferrailleur', slug: 'le-ferrailleur', mood: 'festif' })
const LE_NID = venue({ id: 2, name: 'Le Nid', slug: 'le-nid', mood: 'chill' })

test.beforeEach(async ({ page }) => {
  // La landing affiche aussi la météo : on la stubbe pour ne pas dépendre du backend.
  await page.route('**/api/v1/weather', (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        temp: 14,
        feels_like: 12,
        condition: 'nuit claire',
        icon: '01n',
        wind: 2,
        humidity: 70,
      }),
    }),
  )

  // Liste des lieux : renvoie tout, ou filtré selon le paramètre ?mood=.
  await page.route(
    (url) => url.pathname.endsWith('/api/v1/venues'),
    async (route: Route) => {
      const mood = new URL(route.request().url()).searchParams.get('mood')
      const data = mood === null ? [FERRAILLEUR, LE_NID] : mood === 'festif' ? [FERRAILLEUR] : []
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data }),
      })
    },
  )
})

test('filtre les lieux par ambiance via les pastilles de mood', async ({ page }) => {
  await page.goto('/')

  await expect(page.getByTestId('venue-item')).toHaveCount(2)

  await page.locator('[data-testid="mood-filter"][data-mood="festif"]').click()

  await expect(page.getByTestId('venue-item')).toHaveCount(1)
  await expect(page.getByTestId('venue-item')).toContainText('Le Ferrailleur')

  // Re-cliquer sur la pastille active retire le filtre.
  await page.locator('[data-testid="mood-filter"][data-mood="festif"]').click()
  await expect(page.getByTestId('venue-item')).toHaveCount(2)
})

test('affiche l’état vide quand aucune ambiance ne correspond', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByTestId('venue-item')).toHaveCount(2)

  await page.locator('[data-testid="mood-filter"][data-mood="afterwork"]').click()

  await expect(page.getByTestId('venue-empty')).toBeVisible()
  await expect(page.getByTestId('venue-item')).toHaveCount(0)
})
