import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './app/router'

const app = createApp(App)
app.use(router)
app.mount('#app')

if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      await navigator.serviceWorker.register('/sw.js')
    } catch (error) {
      console.error('Service worker registration failed', error)
    }
  })
}
