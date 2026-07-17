import api from '@/services/api'

const AUTH_TOKEN_KEY = 'auth_token'
const AUTH_USER_KEY = 'auth_user'

export function getStoredToken() {
  return localStorage.getItem(AUTH_TOKEN_KEY)
}

export function getStoredUser() {
  const raw = localStorage.getItem(AUTH_USER_KEY)

  if (!raw) {
    return null
  }

  try {
    return JSON.parse(raw)
  } catch {
    return null
  }
}

export function setAuthSession(token, user) {
  localStorage.setItem(AUTH_TOKEN_KEY, token)
  localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user))
}

export function clearAuthSession() {
  localStorage.removeItem(AUTH_TOKEN_KEY)
  localStorage.removeItem(AUTH_USER_KEY)
}

export async function login(credentials) {
  const baseURL = api.defaults.baseURL
  const rootURL = baseURL.endsWith('/api') ? baseURL.slice(0, -4) : baseURL

  await api.get(`${rootURL}/sanctum/csrf-cookie`)

  const { data } = await api.post('/v1/auth/login', credentials)

  return data
}

export async function register(payload) {
  const baseURL = api.defaults.baseURL
  const rootURL = baseURL.endsWith('/api') ? baseURL.slice(0, -4) : baseURL

  await api.get(`${rootURL}/sanctum/csrf-cookie`)

  const { data } = await api.post('/v1/auth/register', {
    name: payload.name,
    email: payload.email,
    password: payload.password,
    password_confirmation: payload.password_confirmation,
    phone: payload.phone,
  })

  return data
}

export async function logout() {
  try {
    await api.post('/v1/auth/logout')
  } finally {
    clearAuthSession()
  }
}

export async function fetchCurrentUser() {
  const { data } = await api.get('/v1/auth/me')

  return data
}
