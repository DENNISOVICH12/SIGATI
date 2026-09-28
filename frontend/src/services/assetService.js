import api from './api'

/**
 * Limpia un objeto de parámetros eliminando valores null, undefined o strings vacíos.
 *
 * @param {Record<string, any>} params
 * @returns {Record<string, any>}
 */
const cleanParams = (params = {}) => {
  const cleaned = {}

  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined && value !== '') {
      cleaned[key] = typeof value === 'string' ? value.trim() : value
    }
  }

  return cleaned
}

/**
 * Obtiene el listado paginado de activos desde el backend con soporte
 * para búsqueda amplia y filtros combinados.
 *
 * @param {Object} [params]
 * @param {string} [params.search]
 * @param {string} [params.status]
 * @param {string} [params.category]
 * @param {number|string} [params.area_id]
 * @param {number|string} [params.location_id]
 * @param {number} [params.page]
 * @returns {Promise<any>} Objeto paginado estándar de Laravel
 */
export const getAssets = async (params = {}) => {
  const query = cleanParams(params)
  const response = await api.get('/assets', { params: query })
  return response.data
}

/**
 * Obtiene el catálogo de áreas y sus ubicaciones activas.
 *
 * @returns {Promise<Array>} Listado de áreas activas
 */
export const getAreas = async () => {
  const response = await api.get('/areas')
  return response.data.areas ?? []
}

/**
 * Obtiene el listado de categorías únicas existentes de activos.
 *
 * @returns {Promise<Array<string>>} Listado de categorías
 */
export const getAssetCategories = async () => {
  const response = await api.get('/assets/categories')
  return response.data.categories ?? []
}

/**
 * Registra un nuevo activo en el sistema.
 *
 * @param {Object} payload Datos del activo según contrato POST /assets
 * @returns {Promise<any>}
 */
export const createAsset = async (payload) => {
  const response = await api.post('/assets', payload)
  return response.data
}

/**
 * Consulta la información detallada de un activo por su ID.
 *
 * @param {number|string} id ID del activo
 * @returns {Promise<any>} Objeto del activo devuelto por el servidor
 */
export const getAsset = async (id) => {
  const response = await api.get(`/assets/${id}`)
  return response.data.asset ?? response.data
}

/**
 * Actualiza los datos generales de un activo existente.
 *
 * @param {number|string} id ID del activo
 * @param {Object} payload Datos permitidos por PATCH /assets/{id}
 * @returns {Promise<any>}
 */
export const updateAsset = async (id, payload) => {
  const response = await api.patch(`/assets/${id}`, payload)
  return response.data
}

/**
 * Consulta el historial de trazabilidad paginado de un activo.
 *
 * @param {number|string} id   ID del activo
 * @param {number}        page Número de página (default 1)
 * @returns {Promise<any>} Objeto con { asset, history }
 */
export const getAssetHistory = async (id, page = 1) => {
  const response = await api.get(`/assets/${id}/history`, {
    params: { page },
  })
  return response.data
}
/**
 * Traslada un activo a otra área/ubicación.
 *
 * Contrato backend:
 * POST /assets/{id}/transfer
 *
 * @param {number|string} id ID del activo
 * @param {Object} payload
 * @param {number|string} payload.area_id Área destino
 * @param {number|string|null} payload.location_id Ubicación destino
 * @param {string|null} payload.responsible_name Funcionario responsable
 * @param {string} payload.reason Motivo obligatorio del traslado
 * @returns {Promise<any>}
 */
export const transferAsset = async (id, payload) => {
  const response = await api.post(
    `/assets/${id}/transfer`,
    payload,
  )

  return response.data
}

/**
 * Cambia el estado operativo de un activo.
 *
 * Contrato backend:
 * PATCH /assets/{id}/status
 *
 * Estados permitidos:
 * - operational
 * - pending_review
 * - faulty
 * - maintenance
 *
 * @param {number|string} id ID del activo
 * @param {Object} payload
 * @param {string} payload.status Nuevo estado
 * @param {string} payload.reason Motivo obligatorio del cambio
 * @returns {Promise<any>}
 */
export const changeAssetStatus = async (id, payload) => {
  const response = await api.patch(
    `/assets/${id}/status`,
    payload,
  )

  return response.data
}
