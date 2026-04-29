import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import { router } from '@/router'

async function bootstrap(): Promise<void> {
  if (import.meta.env.DEV && import.meta.env.VITE_USE_MOCKS === 'true') {
    const { worker } = await import('@/mocks/browser')
    await worker.start({ onUnhandledRequest: 'bypass' })
  }
  createApp(App).use(router).mount('#app')
}

bootstrap()
