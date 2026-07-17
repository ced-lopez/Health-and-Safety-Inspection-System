import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'

import {
  clearAuthSession,
  fetchCurrentUser,
  getStoredToken,
  getStoredUser,
  login as loginRequest,
  logout as logoutRequest,
  register as registerRequest,
  setAuthSession,
} from '@/services/authService'
import { canAccessModule } from '@/utils/permissions'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(getStoredUser)
  const [loading, setLoading] = useState(true)

  const bootstrap = useCallback(async () => {
    const token = getStoredToken()

    if (!token) {
      setUser(null)
      setLoading(false)
      return
    }

    try {
      const response = await fetchCurrentUser()
      setUser(response.data)
      localStorage.setItem('auth_user', JSON.stringify(response.data))
    } catch {
      clearAuthSession()
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    bootstrap()
  }, [bootstrap])

  const login = useCallback(async (credentials) => {
    const response = await loginRequest(credentials)
    setAuthSession(response.data.token, response.data.user)
    setUser(response.data.user)
    return response
  }, [])

  const register = useCallback(async (payload) => {
    const response = await registerRequest(payload)
    setAuthSession(response.data.token, response.data.user)
    setUser(response.data.user)
    return response
  }, [])

  const logout = useCallback(async () => {
    await logoutRequest()
    setUser(null)
  }, [])

  const canAccess = useCallback(
    (module) => canAccessModule(user?.role?.slug, module),
    [user],
  )

  const value = useMemo(
    () => ({
      user,
      loading,
      isAuthenticated: Boolean(user && getStoredToken()),
      login,
      register,
      logout,
      canAccess,
    }),
    [user, loading, login, register, logout, canAccess],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }

  return context
}
