import type { EventSummary } from './event'

export interface Venue {
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

export interface VenueDetail extends Venue {
  events: EventSummary[]
}
