import api from '@/services/api'

export async function fetchCertificationOptions() {
  const { data } = await api.get('/v1/certifications/options')
  return data
}

export async function fetchCertificationDocuments(params) {
  const { data } = await api.get('/v1/certifications', { params })
  return data
}

export async function createCertificationDocument(payload) {
  const { data } = await api.post('/v1/certifications', payload)
  return data
}

export async function updateCertificationDocument(kind, id, payload) {
  const { data } = await api.put(`/v1/certifications/${kind}/${id}`, payload)
  return data
}

export async function deleteCertificationDocument(kind, id) {
  const { data } = await api.delete(`/v1/certifications/${kind}/${id}`)
  return data
}

export async function approveCertificationDocument(kind, id) {
  const { data } = await api.post(`/v1/certifications/${kind}/${id}/approve`)
  return data
}

export async function revokeCertificationDocument(kind, id) {
  const { data } = await api.post(`/v1/certifications/${kind}/${id}/revoke`)
  return data
}

export async function renewCertificationDocument(kind, id) {
  const { data } = await api.post(`/v1/certifications/${kind}/${id}/renew`)
  return data
}

export function certificationPdfUrl(kind, id) {
  return `/v1/certifications/${kind}/${id}/pdf`
}

export async function verifyQrCode(code) {
  const { data } = await api.get(`/v1/verify/${code}`)
  return data
}
