import axios from 'axios'
import { invalidateSession } from './session'
import { resolveApiBaseUrl } from '@/utils/apiUrl'

const apiBaseUrl = resolveApiBaseUrl(import.meta.env.VITE_API_BASE_URL)

const api = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

/*
 * Antes de cada petición revisamos si existe
 * un token de autenticación.
 *
 * Si existe, lo enviamos automáticamente
 * como Bearer Token a Laravel Sanctum.
 */
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('sigati_token')

    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    return config
  },
  (error) => {
    return Promise.reject(error)
  },
)

/*
 * Interceptor de respuestas.
 *
 * Si Laravel responde 401 significa que el token
 * ya no es válido o la sesión expiró.
 */
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (
      error.response?.status === 401 &&
      localStorage.getItem('sigati_token')
    ) {
      invalidateSession()
    }

    return Promise.reject(error)
  },
)

export default api
