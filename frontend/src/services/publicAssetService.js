import api from './api'

export const getPublicAsset = async (token) => {
  const response = await api.get(`/public/assets/${encodeURIComponent(token)}`)
  return response.data.asset
}

export const createPublicAssetReport = (token, payload) =>
  api.post(`/public/assets/${encodeURIComponent(token)}/reports`, payload)
