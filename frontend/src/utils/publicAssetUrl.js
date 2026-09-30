const HTTP_PROTOCOLS = new Set(['http:', 'https:'])

const parseHttpUrl = (value) => {
  if (!value) return null

  try {
    const url = new URL(value)
    return HTTP_PROTOCOLS.has(url.protocol) ? url : null
  } catch {
    return null
  }
}

export const resolvePublicAppUrl = ({ configuredUrl, browserOrigin }) => {
  const configured = parseHttpUrl(configuredUrl?.trim())
  const fallback = parseHttpUrl(browserOrigin)
  const resolved = configured || fallback

  if (!resolved) throw new Error('No fue posible determinar la URL pública de SIGATI.')

  return resolved.toString().replace(/\/+$/, '')
}

export const buildPublicAssetUrl = (publicPath, options = {}) => {
  const publicAppUrl = resolvePublicAppUrl({
    configuredUrl: options.configuredUrl ?? import.meta.env.VITE_PUBLIC_APP_URL,
    browserOrigin: options.browserOrigin ?? window.location.origin,
  })
  const normalizedPath = `/${String(publicPath).replace(/^\/+/, '')}`

  return `${publicAppUrl}${normalizedPath}`
}

export const isDevelopmentOnlyUrl = (value) => {
  const url = parseHttpUrl(value)
  if (!url) return false

  return ['localhost', '127.0.0.1', '::1', '[::1]'].includes(url.hostname)
}
