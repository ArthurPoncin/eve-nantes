import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'
import { getWeather } from './weather'

const server = setupServer()

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

describe('api-client.getWeather', () => {
  it('GETs /api/v1/weather and returns a typed Weather payload', async () => {
    server.use(
      http.get('*/api/v1/weather', () =>
        HttpResponse.json({
          temp: 12.5,
          feels_like: 10.2,
          condition: 'nuageux',
          icon: '04n',
          wind: 3.4,
          humidity: 78,
        }),
      ),
    )

    const weather = await getWeather()

    expect(weather).toEqual({
      temp: 12.5,
      feels_like: 10.2,
      condition: 'nuageux',
      icon: '04n',
      wind: 3.4,
      humidity: 78,
    })
  })

  it('uses VITE_API_BASE_URL as the request base URL', async () => {
    let requestUrl = ''
    server.use(
      http.get('*/api/v1/weather', ({ request }) => {
        requestUrl = request.url
        return HttpResponse.json({
          temp: 0,
          feels_like: 0,
          condition: '',
          icon: '',
          wind: 0,
          humidity: 0,
        })
      }),
    )

    await getWeather()

    expect(requestUrl).toBe(`${import.meta.env.VITE_API_BASE_URL}/api/v1/weather`)
  })

  it('rejects when the backend responds with 500', async () => {
    server.use(
      http.get('*/api/v1/weather', () =>
        HttpResponse.json({ error: 'boom' }, { status: 500 }),
      ),
    )

    await expect(getWeather()).rejects.toThrow()
  })
})
