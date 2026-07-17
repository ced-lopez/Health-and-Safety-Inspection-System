import api from '@/services/api'

export async function fetchViolationOptions() {
  const { data } = await api.get('/v1/violations/options')
  return data
}

export async function fetchViolations(params) {
  const { data } = await api.get('/v1/violations', { params })
  return data
}

export async function fetchViolation(id) {
  const { data } = await api.get(`/v1/violations/${id}`)
  return data
}

export async function createViolation(payload) {
  const { data } = await api.post('/v1/violations', payload)
  return data
}

export async function updateViolation(id, payload) {
  const { data } = await api.put(`/v1/violations/${id}`, payload)
  return data
}

export async function deleteViolation(id) {
  const { data } = await api.delete(`/v1/violations/${id}`)
  return data
}

export async function uploadViolationEvidence(id, payload) {
  const { data } = await api.post(`/v1/violations/${id}/evidence`, payload, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })

  return data
}
