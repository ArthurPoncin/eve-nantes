export interface TransportStop {
  code: string
  name: string
  distance: string | null
}

export interface TransportDeparture {
  line: string
  type: 'tram' | 'busway' | 'bus' | 'navibus'
  terminus: string
  wait: string
  realtime: boolean
}

export interface VenueTransport {
  stop: TransportStop | null
  departures: TransportDeparture[]
}
