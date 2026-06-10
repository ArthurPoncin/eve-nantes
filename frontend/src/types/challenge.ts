/** Défi mensuel, avec la progression de l'utilisateur (« 3/5 »). */
export interface Challenge {
  id: string
  label: string
  description: string
  icon: string
  goal: number
  progress: number
  completed: boolean
  completed_at: string | null
  ends_at: string
}
