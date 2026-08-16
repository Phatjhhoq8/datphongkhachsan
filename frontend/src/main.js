import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { installPageTracking } from './analytics'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia).use(router)
installPageTracking(router)
app.mount('#app')
