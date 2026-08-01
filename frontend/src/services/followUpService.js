import api from '@/services/api'

export async function fetchFollowUps(params) {
  const { data } = await api.get('/v1/follow-up', { params })
  return data
}

export async function requestFollowUp(payload) {
  const { data } = await api.post('/v1/follow-up', payload)
  return data
}

export async function fetchFollowUp(requestId) {
  const { data } = await api.get(`/v1/follow-up/${requestId}`)
  return data
}
