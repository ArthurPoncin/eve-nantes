import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import { router } from '@/router'
import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'

const app = createApp(App)
app.use(createPinia())
app.use(router)

// Réhydrate l'utilisateur courant si un token Sanctum est déjà persisté.
const auth = useAuthStore()
if (auth.isAuthenticated) {
  auth.loadMe().catch(() => auth.logout())
  useFavoritesStore().load().catch(() => {})
}

app.mount('#app')
