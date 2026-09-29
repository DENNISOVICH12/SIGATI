export const TICKET_STATUS_LABELS = Object.freeze({
  new: 'Nuevo',
  assigned: 'Asignado',
  in_progress: 'En proceso',
  resolved: 'Resuelto',
  closed: 'Cerrado',
  on_hold: 'En espera',
  reopened: 'Reabierto',
})

export const TICKET_PRIORITY_LABELS = Object.freeze({
  low: 'Baja',
  medium: 'Media',
  high: 'Alta',
  critical: 'Crítica',
})

export const TICKET_EVENT_LABELS = Object.freeze({
  created: 'Ticket creado',
  claimed: 'Servicio tomado',
  released: 'Servicio liberado',
  assigned: 'Técnico asignado',
  reassigned: 'Técnico reasignado',
  started: 'Atención iniciada',
  resolved: 'Servicio resuelto',
  closed: 'Ticket cerrado',
})

const fallbackLabel = (value) => value || 'Sin información'

export const getTicketStatusLabel = (status) =>
  TICKET_STATUS_LABELS[status] ?? fallbackLabel(status)

export const getTicketPriorityLabel = (priority) =>
  TICKET_PRIORITY_LABELS[priority] ?? fallbackLabel(priority)

export const getTicketEventLabel = (eventType) =>
  TICKET_EVENT_LABELS[eventType] ?? fallbackLabel(eventType)
