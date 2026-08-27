import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import { api, getToken, setToken, setUnauthorizedHandler } from './api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setUnauthorizedHandler(() => setUser(null))

    if (!getToken()) {
      setLoading(false)
      return
    }

    api
      .get('/me')
      .then(({ data }) => setUser(data.user))
      .catch(() => setToken(null))
      .finally(() => setLoading(false))
  }, [])

  const value = useMemo(
    () => ({
      user,
      loading,
      async login(email, password) {
        const { data } = await api.post('/login', { email, password })
        setToken(data.token)
        setUser(data.user)
        return data.user
      },
      async logout() {
        try {
          await api.post('/logout')
        } catch {
          // token may already be gone; log out locally either way
        }
        setToken(null)
        setUser(null)
      },
    }),
    [user, loading],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>')
  return ctx
}

/** Where each role lands after logging in. */
export function homeFor(user) {
  if (!user) return '/login'
  if (user.role === 'super_admin') return '/super'
  if (user.role === 'admin') return '/admin'
  if (user.role === 'manager') return '/manager'
  return '/employee'
}
