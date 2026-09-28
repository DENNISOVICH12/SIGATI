import { ref, computed } from 'vue'

import {
  login as apiLogin,
  getCurrentUser as apiGetCurrentUser,
  logout as apiLogout,
  getStoredUser,
  getToken,
} from '@/services/authService'

/*
 * =========================================================
 * ESTADO GLOBAL DE AUTENTICACIÓN
 * =========================================================
 *
 * Estas variables se encuentran fuera de useAuth()
 * intencionalmente.
 *
 * De esta manera todos los componentes, vistas y guards
 * utilizan exactamente la misma instancia reactiva.
 */

const user = ref(getStoredUser())

const loading = ref(false)

const initialized = ref(false)

/*
 * El estado reactivo del usuario es la fuente de verdad
 * del frontend.
 *
 * Si existe user.value significa que la sesión ya fue
 * validada mediante /api/me.
 */
const isAuthenticated = computed(() => {
  return user.value !== null
})

/*
 * =========================================================
 * INICIALIZACIÓN DE SESIÓN
 * =========================================================
 *
 * Se ejecuta al cargar la aplicación.
 *
 * Si existe un token almacenado, se valida contra Laravel
 * mediante GET /api/me.
 */
const initAuth = async () => {
  /*
   * Evita inicializar la sesión varias veces.
   */
  if (initialized.value) {
    return user.value
  }

  const token = getToken()

  /*
   * No existe token.
   * No hay sesión que restaurar.
   */
  if (!token) {
    user.value = null
    initialized.value = true

    return null
  }

  loading.value = true

  try {
    /*
     * Validamos el token almacenado contra Laravel.
     */
    const currentUser =
      await apiGetCurrentUser()

    /*
     * /api/me es la fuente de verdad para:
     *
     * - usuario
     * - roles
     * - permisos
     */
    user.value = currentUser

    return currentUser
  } catch (error) {
    /*
     * Token inválido, expirado o rechazado.
     */
    user.value = null

    return null
  } finally {
    loading.value = false
    initialized.value = true
  }
}

/*
 * =========================================================
 * LOGIN
 * =========================================================
 */
const signIn = async (
  email,
  password,
) => {
  loading.value = true

  try {
    /*
     * Laravel valida las credenciales y devuelve
     * el token de Sanctum.
     *
     * authService se encarga de almacenarlo.
     */
    await apiLogin(
      email,
      password,
    )

    /*
     * Inmediatamente consultamos /api/me.
     *
     * Esto garantiza que el frontend trabaje con
     * roles y permisos provenientes del backend.
     */
    const currentUser =
      await apiGetCurrentUser()

    if (!currentUser) {
      throw new Error(
        'No fue posible obtener el usuario autenticado.',
      )
    }

    /*
     * Actualizamos primero el estado reactivo.
     *
     * A partir de este momento:
     *
     * isAuthenticated.value === true
     */
    user.value = currentUser

    /*
     * La sesión ya fue validada contra Laravel,
     * por lo que no es necesario que el router
     * vuelva a inicializarla.
     */
    initialized.value = true

    return currentUser
  } catch (error) {
    /*
     * Si algo falla durante el login no dejamos
     * un usuario parcialmente autenticado.
     */
    user.value = null

    throw error
  } finally {
    loading.value = false
  }
}

/*
 * =========================================================
 * LOGOUT
 * =========================================================
 */
const signOut = async () => {
  loading.value = true

  try {
    await apiLogout()
  } finally {
    /*
     * Eliminamos inmediatamente el estado reactivo.
     */
    user.value = null

    /*
     * La aplicación ya conoce el estado actual:
     * usuario sin sesión.
     */
    initialized.value = true

    loading.value = false
  }
}

/*
 * =========================================================
 * PERMISOS
 * =========================================================
 */
const can = (
  permission,
) => {
  if (
    !user.value ||
    !Array.isArray(
      user.value.permissions,
    )
  ) {
    return false
  }

  return user.value.permissions.includes(
    permission,
  )
}

/*
 * =========================================================
 * ROLES
 * =========================================================
 */
const hasRole = (
  role,
) => {
  if (
    !user.value ||
    !Array.isArray(
      user.value.roles,
    )
  ) {
    return false
  }

  return user.value.roles.includes(
    role,
  )
}

/*
 * =========================================================
 * COMPOSABLE
 * =========================================================
 */
export function useAuth() {
  return {
    user,
    loading,
    initialized,
    isAuthenticated,

    initAuth,
    signIn,
    signOut,

    can,
    hasRole,
  }
}