import type { EventSummary } from './event'
import type { Venue } from './venue'
import type { Weather } from './weather'

// Suggestion de soirée composée par le backend : un lieu, son éventuel
// prochain événement, la météo du soir et une narration IA.
export interface Soiree {
  mood: string
  venue: Venue
  event: EventSummary | null
  weather: Weather
  narrative: string
}
