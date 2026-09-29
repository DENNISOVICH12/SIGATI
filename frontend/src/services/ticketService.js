import api from './api'

const TICKET_FILTERS = new Set([
  'status',
  'priority',
  'assigned_to',
  'unassigned',
  'sla',
  'response_sla',
  'resolution_sla',
  'search',
  'per_page',
  'page',
])

const cleanTicketParams = (params = {}) =>
  Object.fromEntries(
    Object.entries(params).filter(
      ([key, value]) =>
        TICKET_FILTERS.has(key) &&
        value !== undefined &&
        value !== null &&
        value !== '',
    ),
  )

export const getTickets = (params = {}) =>
  api.get('/tickets', { params: cleanTicketParams(params) })

export const getTicket = (id) => api.get(`/tickets/${id}`)

export const createTicket = (payload) => api.post('/tickets', payload)

export const getTicketStats = () => api.get('/tickets/stats')

export const getAssignableTechnicians = () => api.get('/users/technicians')

export const claimTicket = (id) => api.post(`/tickets/${id}/claim`)

export const releaseTicket = (id, payload) =>
  api.post(`/tickets/${id}/release`, payload)

export const assignTicket = (id, payload) =>
  api.post(`/tickets/${id}/assign`, payload)

export const startTicket = (id, payload) =>
  api.post(`/tickets/${id}/start`, payload)

export const resolveTicket = (id, payload) =>
  api.post(`/tickets/${id}/resolve`, payload)

export const closeTicket = (id, payload) =>
  api.post(`/tickets/${id}/close`, payload)
