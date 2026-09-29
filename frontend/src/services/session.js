const invalidationListeners = new Set()

export const clearStoredSession = () => {
  localStorage.removeItem('sigati_token')
  localStorage.removeItem('sigati_user')
}

export const invalidateSession = () => {
  clearStoredSession()
  invalidationListeners.forEach((listener) => listener())
}

export const onSessionInvalidated = (listener) => {
  invalidationListeners.add(listener)

  return () => invalidationListeners.delete(listener)
}
