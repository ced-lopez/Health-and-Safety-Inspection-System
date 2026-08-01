import axios from 'axios'

import { clearAuthSession, getStoredToken, getStoredUser } from '@/services/authService'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const hadSession = Boolean(getStoredToken())
      const storedUser = getStoredUser()

      clearAuthSession()

      // If the session became invalid (expired/revoked token) while the user was
      // authenticated, reset the whole app to the login page. Clearing storage is
      // not enough on its own because the in-memory auth context still thinks the
      // user is logged in, which triggers a loop of failing protected requests.
      if (hadSession) {
        const loginPath = storedUser?.role?.slug === 'resident' ? '/login' : '/admin/login'
        window.location.assign(loginPath)
      }
    }

    return Promise.reject(error)
  },
)

export default api
