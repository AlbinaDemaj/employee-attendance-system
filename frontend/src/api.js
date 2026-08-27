import axios from 'axios'

// Ne zhvillim kjo lihet bosh dhe kerkesat kalojne te proxy-i i Vite-s, pra
// mbeten ne te njejten origjine ('/api'). Ne prodhim VITE_API_BASE_URL tregon
// te backend-i, p.sh. https://prezenca-api.onrender.com
//
// Pranohet me ose pa '/api' ne fund, qe te dyja format e zakonshme te punojne.
// VITE_API_URL mbetet si emer i vjeter, per konfigurimet ekzistuese lokale.
const apiOrigin = (
  import.meta.env.VITE_API_BASE_URL ||
  import.meta.env.VITE_API_URL ||
  ''
)
  .trim()
  .replace(/\/+$/, '')
  // Nje adresa e ngjitur pa skeme ('api.onrender.com') do te trajtohej si
  // shteg relativ nga axios-i, prandaj e plotesojme.
  .replace(/^(?!https?:\/\/)(?=.)/, 'https://')

/** True kur flasim me nje origjine tjeter, jo me proxy-in e Vite-s. */
export const usesExternalApi = apiOrigin !== ''

const baseURL = usesExternalApi
  ? (apiOrigin.endsWith('/api') ? apiOrigin : `${apiOrigin}/api`)
  : '/api'

export const api = axios.create({
  baseURL,
  headers: { Accept: 'application/json' },
})

const TOKEN_KEY = 'attendance_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

let onUnauthorized = null
export function setUnauthorizedHandler(fn) {
  onUnauthorized = fn
}

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      setToken(null)
      onUnauthorized?.()
    }
    return Promise.reject(error)
  },
)

/** Turn a Laravel error response into one readable line. */
export function errorMessage(error, fallback = 'Diçka shkoi keq. Provo sërish.') {
  const data = error?.response?.data
  if (!data) return error?.message || fallback
  if (data.errors) {
    const first = Object.values(data.errors)[0]
    if (Array.isArray(first) && first[0]) return first[0]
  }
  return data.message || fallback
}

/**
 * Certificates come back as absolute backend URLs. When we go through the Vite
 * proxy we want them same-origin, so keep only the path.
 */
export function assetUrl(url) {
  if (!url) return null
  // Me nje backend te jashtem URL-ja absolute eshte e sakte ashtu si vjen.
  if (usesExternalApi) return url
  // Me proxy-in e Vite-s e duam te njejten origjine, pra mbajme vetem shtegun.
  try {
    return new URL(url).pathname
  } catch {
    return url
  }
}
