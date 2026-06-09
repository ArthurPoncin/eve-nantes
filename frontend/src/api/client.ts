import axios from 'axios'
import { getToken } from './token-storage'

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
})

// Joint le token Sanctum aux requêtes sortantes quand l'utilisateur est connecté.
apiClient.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})
