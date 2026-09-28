import { createApp } from 'vue'

import App from './App.vue'
import router from './router'

import './style.css'

const app = createApp(App)

app.use(router)

/*
 * Esperamos a que Vue Router esté preparado
 * antes de montar la aplicación.
 *
 * La hidratación de la sesión queda centralizada
 * en el Navigation Guard de src/router/index.js.
 */
router.isReady().then(() => {
  app.mount('#app')
})