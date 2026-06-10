import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'
import { checkIn, closeViree, fetchCurrentViree, fetchViree, fetchVirees } from './virees'
import type { Viree } from '@/types/viree'

const server = setupServer()

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

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
    checkins: [
      {
        id: 1,
        happened_at: '2026-06-10T21:00:00+02:00',
        venue: {
          id: 1,
          name: 'Le Macadam',
          slug: 'le-macadam',
          mood: 'festif',
          latitude: 47.2,
          longitude: -1.56,
        },
      },
    ],
    ...overrides,
  }
}

describe('api/virees', () => {
  it('checkIn POSTs to the venue checkin endpoint and unwraps data', async () => {
    let pathname = ''
    server.use(
      http.post('*/api/v1/venues/le-macadam/checkin', ({ request }) => {
        pathname = new URL(request.url).pathname
        return HttpResponse.json({ data: makeViree() }, { status: 201 })
      }),
    )

    const viree = await checkIn('le-macadam')

    expect(pathname).toBe('/api/v1/venues/le-macadam/checkin')
    expect(viree.status).toBe('en_cours')
  })

  it('fetchCurrentViree returns null when no viree is active', async () => {
    server.use(
      http.get('*/api/v1/virees/current', () => HttpResponse.json({ data: null })),
    )

    expect(await fetchCurrentViree()).toBeNull()
  })

  it('closeViree POSTs to close and returns the recap', async () => {
    server.use(
      http.post('*/api/v1/virees/current/close', () =>
        HttpResponse.json({ data: makeViree({ status: 'terminee' }) }),
      ),
    )

    const recap = await closeViree()

    expect(recap.status).toBe('terminee')
  })

  it('fetchVirees lists completed virees', async () => {
    server.use(
      http.get('*/api/v1/virees', () =>
        HttpResponse.json({ data: [makeViree({ status: 'terminee' })] }),
      ),
    )

    expect(await fetchVirees()).toHaveLength(1)
  })

  it('fetchViree GETs the public recap by its public id', async () => {
    let pathname = ''
    server.use(
      http.get('*/api/v1/virees/aaaa-bbbb', ({ request }) => {
        pathname = new URL(request.url).pathname
        return HttpResponse.json({ data: makeViree() })
      }),
    )

    const viree = await fetchViree('aaaa-bbbb')

    expect(pathname).toBe('/api/v1/virees/aaaa-bbbb')
    expect(viree.public_id).toBe('aaaa-bbbb')
  })
})
