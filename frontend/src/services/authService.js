import api from './api'

export const login = async (email, password) => {
  const response = await api.post('/login', {
    email,
    password,
  })

  const data = response.data

  if (data.token) {
    localStorage.setItem(
      'sigati_token',
      data.token,
    )
  }

  /*
   * Laravel puede devolver información del usuario
   * durante el login.
   */
  if (data.user) {
    localStorage.setItem(
      'sigati_user',
      JSON.stringify(data.user),
    )
  }

  return data
}

export const getCurrentUser = async () => {
  const response = await api.get('/me')

  /*
   * /api/me devuelve:
   *
   * {
   *   user: {
   *     id,
   *     name,
   *     email,
   *     roles,
   *     permissions
   *   }
   * }
   *
   * Por eso devolvemos response.data.user
   * y no response.data completo.
   */
  const user = response.data.user

  if (user) {
    localStorage.setItem(
      'sigati_user',
      JSON.stringify(user),
    )
  }

  return user
}

export const logout = async () => {
  try {
    await api.post('/logout')
  } finally {
    localStorage.removeItem('sigati_token')
    localStorage.removeItem('sigati_user')
  }
}

export const getStoredUser = () => {
  const user = localStorage.getItem(
    'sigati_user',
  )

  if (!user) {
    return null
  }

  try {
    return JSON.parse(user)
  } catch {
    localStorage.removeItem('sigati_user')
    return null
  }
}

export const getToken = () => {
  return localStorage.getItem('sigati_token')
}