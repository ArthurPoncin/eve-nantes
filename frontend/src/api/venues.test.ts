import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'
import { fetchVenue, fetchVenues } from './venues'

const server = setupServer()

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

const sampleVenue = {
  id: 1,
  name: 'Le Lieu Unique',
  slug: 'le-lieu-unique',
  address_line: '2 Rue de la Biscuiterie',
  postal_code: '44000',
  city: 'Nantes',
  mood: 'festif',
  capacity: 800,
  latitude: 47.2138,
  longitude: -1.5436,
}

describe('api-client.fetchVenues', () => {
  it('GETs /api/v1/venues and returns the unwrapped data array', async () => {
    server.use(
      http.get('*/api/v1/venues', () => HttpResponse.json({ data: [sampleVenue] })),
    )

    const venues = await fetchVenues()

    expect(venues).toEqual([sampleVenue])
  })

  it('sends ?mood=festif in the request URL when called with a mood', async () => {
    let requestUrl = ''
    server.use(
      http.get('*/api/v1/venues', ({ request }) => {
        requestUrl = request.url
        return HttpResponse.json({ data: [] })
      }),
    )

    await fetchVenues('festif')

    expect(new URL(requestUrl).searchParams.get('mood')).toBe('festif')
  })

  it('sends no mood query param when called without a mood', async () => {
    let requestUrl = ''
    server.use(
      http.get('*/api/v1/venues', ({ request }) => {
        requestUrl = request.url
        return HttpResponse.json({ data: [] })
      }),
    )

    await fetchVenues()

    expect(new URL(requestUrl).searchParams.has('mood')).toBe(false)
  })

  it('rejects when the backend responds with 500', async () => {
    server.use(
      http.get('*/api/v1/venues', () =>
        HttpResponse.json({ error: 'boom' }, { status: 500 }),
      ),
    )

    await expect(fetchVenues()).rejects.toThrow()
  })
})

const sampleVenueDetail = {
  ...sampleVenue,
  id: 2,
  name: 'Stereolux',
  slug: 'stereolux',
  events: [
    {
      id: 10,
      title: 'Nuit Électronique',
      slug: 'nuit-electronique',
      description: 'Une nuit de musique électronique.',
      starts_at: '2026-06-20T21:00:00.000Z',
      ends_at: '2026-06-21T04:00:00.000Z',
      price_cents: 1500,
    },
  ],
}

describe('api-client.fetchVenue', () => {
  it('GETs /api/v1/venues/{slug} and returns the unwrapped venue with its events', async () => {
    let requestUrl = ''
    server.use(
      http.get('*/api/v1/venues/stereolux', ({ request }) => {
        requestUrl = request.url
        return HttpResponse.json({ data: sampleVenueDetail })
      }),
    )

    const venue = await fetchVenue('stereolux')

    expect(new URL(requestUrl).pathname).toBe('/api/v1/venues/stereolux')
    expect(venue).toEqual(sampleVenueDetail)
    expect(venue.events).toHaveLength(1)
  })

  it('rejects when the slug is unknown (404)', async () => {
    server.use(
      http.get('*/api/v1/venues/inconnu', () =>
        HttpResponse.json({ error: 'not found' }, { status: 404 }),
      ),
    )

    await expect(fetchVenue('inconnu')).rejects.toThrow()
  })
})
