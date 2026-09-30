import assert from 'node:assert/strict'
import test from 'node:test'

import { resolveApiBaseUrl } from '../src/utils/apiUrl.js'
import {
  buildPublicAssetUrl,
  isDevelopmentOnlyUrl,
  resolvePublicAppUrl,
} from '../src/utils/publicAssetUrl.js'

const token = 'e143be6a-3e04-4eb2-b257-a275656fa016'
const path = `/a/${token}`

test('la URL configurada tiene prioridad y normaliza la barra final', () => {
  assert.equal(
    buildPublicAssetUrl(path, {
      configuredUrl: 'https://sigati.example.com/',
      browserOrigin: 'http://10.10.10.10:5173',
    }),
    `https://sigati.example.com${path}`,
  )
})

test('el origen actual es el fallback y conserva el token al cambiar de red', () => {
  const first = buildPublicAssetUrl(path, { configuredUrl: '', browserOrigin: 'http://10.0.0.20:5173' })
  const second = buildPublicAssetUrl(path, { configuredUrl: '', browserOrigin: 'http://10.0.1.35:5173' })

  assert.equal(first, `http://10.0.0.20:5173${path}`)
  assert.equal(second, `http://10.0.1.35:5173${path}`)
  assert.ok(first.endsWith(token))
  assert.ok(second.endsWith(token))
  assert.doesNotMatch(first, /\/\/a\//)
})

test('una configuración inválida usa el origen del navegador', () => {
  assert.equal(
    resolvePublicAppUrl({ configuredUrl: 'javascript:alert(1)', browserOrigin: 'https://sigati.example.com' }),
    'https://sigati.example.com',
  )
})

test('detecta únicamente orígenes loopback como etiquetas de desarrollo', () => {
  assert.equal(isDevelopmentOnlyUrl(`http://localhost:5173${path}`), true)
  assert.equal(isDevelopmentOnlyUrl(`http://127.0.0.1:5173${path}`), true)
  assert.equal(isDevelopmentOnlyUrl(`http://[::1]:5173${path}`), true)
  assert.equal(isDevelopmentOnlyUrl(`http://10.0.0.20:5173${path}`), false)
  assert.equal(isDevelopmentOnlyUrl(`https://sigati.example.com${path}`), false)
})

test('la API usa la ruta relativa del proxy salvo configuración explícita', () => {
  assert.equal(resolveApiBaseUrl(), '/api')
  assert.equal(resolveApiBaseUrl('  '), '/api')
  assert.equal(resolveApiBaseUrl('https://api.example.com/api'), 'https://api.example.com/api')
})
