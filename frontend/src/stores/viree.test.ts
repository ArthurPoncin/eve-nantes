import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'
import { createPinia, setActivePinia } from 'pinia'
import { useVireeStore } from './viree'
import type { Checkin, Viree } from '@/types/viree'

const server = setupServer()

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

function makeCheckin(slug: string, id = 1): Checkin {
  return {
    id,
    happened_at: '2026-06-10T21:00:00+02:00',
    venue: {
      id,
      name: slug,
      slug,
      mood: 'festif',
      latitude: 47.2,
      longitude: -1.56,
    },
  }
}

function makeViree(overrides: Partial<Viree> = {}): Viree {
  return {
    id: 1,
    public_id: 'aaaa-bbbb',
    is_public: true,
    status: 'en_cours',
    started_at: '2026-06-10T21:00:00+02:00',
    ended_at: null,
    stats: { venues: 1, distance_m: null, duration_min: 30, moods: ['festif'] },
    narrative: null,
    weather: null,
    checkins: [makeCheckin('le-macadam')],
    ...overrides,
  }
}

describe('viree store', () => {
  it('load() hydrates the current viree', async () => {
    server.use(
      http.get('*/api/v1/virees/current', () => HttpResponse.json({ data: makeViree() })),
    )

    const store = useVireeStore()
    await store.load()

    expect(store.loaded).toBe(true)
    expect(store.isActive).toBe(true)
    expect(store.venuesCount).toBe(1)
    expect(store.lastVenueSlug).toBe('le-macadam')
  })

  it('load() leaves the store inactive when no viree is running', async () => {
    server.use(
      http.get('*/api/v1/virees/current', () => HttpResponse.json({ data: null })),
    )

    const store = useVireeStore()
    await store.load()

    expect(store.loaded).toBe(true)
    expect(store.isActive).toBe(false)
    expect(store.lastVenueSlug).toBeNull()
  })

  it('checkIn() replaces the current viree with the API response', async () => {
    server.use(
      http.post('*/api/v1/venues/stereolux/checkin', () =>
        HttpResponse.json(
          {
            data: makeViree({
              stats: { venues: 2, distance_m: null, duration_min: 60, moods: ['festif'] },
              checkins: [makeCheckin('le-macadam'), makeCheckin('stereolux', 2)],
            }),
          },
          { status: 201 },
        ),
      ),
    )

    const store = useVireeStore()
    await store.checkIn('stereolux')

    expect(store.venuesCount).toBe(2)
    expect(store.lastVenueSlug).toBe('stereolux')
  })

  it('close() clears the current viree and returns the recap', async () => {
    server.use(
      http.get('*/api/v1/virees/current', () => HttpResponse.json({ data: makeViree() })),
      http.post('*/api/v1/virees/current/close', () =>
        HttpResponse.json({ data: makeViree({ status: 'terminee' }) }),
      ),
    )

    const store = useVireeStore()
    await store.load()
    const recap = await store.close()

    expect(recap.status).toBe('terminee')
    expect(store.isActive).toBe(false)
  })

  it('reset() drops state and the loaded flag', async () => {
    server.use(
      http.get('*/api/v1/virees/current', () => HttpResponse.json({ data: makeViree() })),
    )

    const store = useVireeStore()
    await store.load()
    store.reset()

    expect(store.isActive).toBe(false)
    expect(store.loaded).toBe(false)
  })
})
