import { http, HttpResponse } from 'msw'
import type { Weather } from '@/types/weather'

const weatherFixture: Weather = {
  temp: 13.4,
  feels_like: 11.1,
  condition: 'nuit claire',
  icon: '01n',
  wind: 2.6,
  humidity: 72,
}

export const handlers = [
  http.get('*/api/v1/weather', () => HttpResponse.json(weatherFixture)),
]
