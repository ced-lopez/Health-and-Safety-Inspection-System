import api from '@/services/api'

export async function fetchOcrResults(params) {
  const { data } = await api.get('/v1/admin/ocr-results', { params })
  return data
}

export async function fetchOcrResultStats() {
  const { data } = await api.get('/v1/admin/ocr-results/stats')
  return data
}

export async function fetchOcrResult(id) {
  const { data } = await api.get(`/v1/admin/ocr-results/${id}`)
  return data
}

export async function fetchOcrResultHistory(id) {
  const { data } = await api.get(`/v1/admin/ocr-results/${id}/history`)
  return data
}

export async function updateOcrResultFields(id, payload) {
  const { data } = await api.patch(`/v1/admin/ocr-results/${id}/fields`, payload)
  return data
}

export async function verifyOcrResult(id) {
  const { data } = await api.post(`/v1/admin/ocr-results/${id}/verify`)
  return data
}

export async function rejectOcrResult(id, payload) {
  const { data } = await api.post(`/v1/admin/ocr-results/${id}/reject`, payload)
  return data
}

export async function requestOcrResultReupload(id) {
  const { data } = await api.post(`/v1/admin/ocr-results/${id}/request-reupload`)
  return data
}

export async function reprocessOcrResult(id) {
  const { data } = await api.post(`/v1/admin/ocr-results/${id}/reprocess`)
  return data
}