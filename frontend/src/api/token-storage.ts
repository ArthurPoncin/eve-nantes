// Persistance du token Sanctum, isolée dans son propre module pour éviter un
// cycle d'imports entre le client Axios (intercepteur) et le store Pinia.
const STORAGE_KEY = 'noctambule.token'

export function getToken(): string | null {
  return localStorage.getItem(STORAGE_KEY)
}

export function setToken(token: string): void {
  localStorage.setItem(STORAGE_KEY, token)
}

export function clearToken(): void {
  localStorage.removeItem(STORAGE_KEY)
}
