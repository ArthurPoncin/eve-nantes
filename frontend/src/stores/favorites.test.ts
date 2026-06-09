import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'
import { createPinia, setActivePinia } from 'pinia'
import { useFavoritesStore } from './favorites'
import type { Venue } from '@/types/venue'

const server = setupServer()

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

function makeVenue(overrides: Partial<Venue> = {}): Venue {
  return {
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
    ...overrides,
  }
}

const lieuUnique = makeVenue({ id: 1, name: 'Le Lieu Unique', slug: 'le-lieu-unique' })
const stereolux = makeVenue({ id: 2, name: 'Stereolux', slug: 'stereolux' })

describe('favorites store', () => {
  it('load() fills venues and slugs from GET /api/v1/favorites', async () => {
    server.use(
      http.get('*/api/v1/favorites', () =>
        HttpResponse.json({ data: [lieuUnique, stereolux] }),
      ),
    )

    const favorites = useFavoritesStore()
    await favorites.load()

    expect(favorites.loaded).toBe(true)
    expect(favorites.venues).toEqual([lieuUnique, stereolux])
    expect(favorites.slugs.has('le-lieu-unique')).toBe(true)
    expect(favorites.slugs.has('stereolux')).toBe(true)
  })

  it('isFavorite reflects the loaded data', async () => {
    server.use(
      http.get('*/api/v1/favorites', () => HttpResponse.json({ data: [lieuUnique] })),
    )

    const favorites = useFavoritesStore()
    await favorites.load()

    expect(favorites.isFavorite('le-lieu-unique')).toBe(true)
    expect(favorites.isFavorite('stereolux')).toBe(false)
  })

  it('load() is safe to call repeatedly', async () => {
    server.use(
      http.get('*/api/v1/favorites', () => HttpResponse.json({ data: [lieuUnique] })),
    )

    const favorites = useFavoritesStore()
    await favorites.load()
    await favorites.load()

    expect(favorites.venues).toHaveLength(1)
    expect(favorites.slugs.size).toBe(1)
  })

  it('toggle on a non-favorite POSTs and marks it favorite', async () => {
    let postCalled = false
    let postUrl = ''
    server.use(
      http.post('*/api/v1/venues/stereolux/favorite', ({ request }) => {
        postCalled = true
        postUrl = request.url
        return new HttpResponse(null, { status: 201 })
      }),
    )

    const favorites = useFavoritesStore()
    await favorites.toggle(stereolux)

    expect(postCalled).toBe(true)
    expect(new URL(postUrl).pathname).toBe('/api/v1/venues/stereolux/favorite')
    expect(favorites.isFavorite('stereolux')).toBe(true)
    expect(favorites.venues).toContainEqual(stereolux)
  })

  it('toggle on a favorite DELETEs and unmarks it', async () => {
    server.use(
      http.get('*/api/v1/favorites', () => HttpResponse.json({ data: [stereolux] })),
    )
    let deleteCalled = false
    let deleteUrl = ''
    server.use(
      http.delete('*/api/v1/venues/stereolux/favorite', ({ request }) => {
        deleteCalled = true
        deleteUrl = request.url
        return new HttpResponse(null, { status: 204 })
      }),
    )

    const favorites = useFavoritesStore()
    await favorites.load()
    expect(favorites.isFavorite('stereolux')).toBe(true)

    await favorites.toggle(stereolux)

    expect(deleteCalled).toBe(true)
    expect(new URL(deleteUrl).pathname).toBe('/api/v1/venues/stereolux/favorite')
    expect(favorites.isFavorite('stereolux')).toBe(false)
    expect(favorites.venues).toHaveLength(0)
  })
})
