import api from '@/services/api'

export async function fetchDocument(id) {
  const { data } = await api.get(`/v1/documents/${id}`)
  return data
}

export async function fetchDocumentFile(id) {
  const { data } = await api.get(`/v1/documents/${id}/download`, { responseType: 'blob' })
  return data
}

export async function processDocumentOcr(id) {
  const { data } = await api.post(`/v1/documents/${id}/process-ocr`)
  return data
}

export async function verifyDocument(id, payload) {
  const { data } = await api.put(`/v1/documents/${id}/verify`, payload)
  return data
}
