import axios from 'axios'

// Defaults to the Vite proxy (same origin). Set VITE_API_URL in frontend/.env
// to call the backend directly, e.g. http://127.0.0.1:8000/api
const baseURL = import.meta.env.VITE_API_URL || '/api'

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
  if (import.meta.env.VITE_API_URL) return url
  try {
    return new URL(url).pathname
  } catch {
    return url
  }
}
