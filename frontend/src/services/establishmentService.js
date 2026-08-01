import api from '@/services/api'

export async function fetchEstablishments(params) {
  const { data } = await api.get('/v1/establishments', { params })
  return data
}

export async function fetchEstablishment(id) {
  const { data } = await api.get(`/v1/establishments/${id}`)
  return data
}

export async function createEstablishment(payload) {
  const { data } = await api.post('/v1/establishments', payload)
  return data
}

export async function updateEstablishment(id, payload) {
  const { data } = await api.put(`/v1/establishments/${id}`, payload)
  return data
}

export async function deleteEstablishment(id) {
  const { data } = await api.delete(`/v1/establishments/${id}`)
  return data
}

export async function fetchPendingClaims() {
  const { data } = await api.get('/v1/establishment-claims')
  return data
}

export async function approveClaim(id) {
  const { data } = await api.post(`/v1/establishment-claims/${id}/approve`)
  return data
}

export async function rejectClaim(id) {
  const { data } = await api.post(`/v1/establishment-claims/${id}/reject`)
  return data
}
