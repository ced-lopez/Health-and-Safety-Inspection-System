import api from '@/services/api'

export async function fetchInspectionRequestOptions() {
  const { data } = await api.get('/v1/inspection-requests/options')
  return data
}

export async function fetchDocumentRequirements(params) {
  const { data } = await api.get('/v1/inspection-requests/document-requirements', { params })
  return data
}

export async function fetchInspectionRequests(params) {
  const { data } = await api.get('/v1/inspection-requests', { params })
  return data
}

export async function fetchInspectionRequest(id) {
  const { data } = await api.get(`/v1/inspection-requests/${id}`)
  return data
}

export async function createInspectionRequest(payload) {
  const { data } = await api.post('/v1/inspection-requests', payload)
  return data
}

export async function reviewInspectionRequest(id, payload) {
  const { data } = await api.put(`/v1/inspection-requests/${id}/review`, payload)
  return data
}

export async function assignInspectionRequest(id, payload) {
  const { data } = await api.post(`/v1/inspection-requests/${id}/assign`, payload)
  return data
}

export async function fetchInspectionQueue(params) {
  const { data } = await api.get('/v1/inspection-requests/queue', { params })
  return data
}

export async function fetchRequestRequirements(requestId) {
  const { data } = await api.get(`/v1/inspection-requests/${requestId}/requirements`)
  return data
}

export async function fetchRequestDocuments(requestId) {
  const { data } = await api.get(`/v1/inspection-requests/${requestId}/documents`)
  return data
}

export async function fetchRequestDocumentFile(requestId, documentId) {
  const { data } = await api.get(`/v1/inspection-requests/${requestId}/documents/${documentId}/download`, {
    responseType: 'blob',
  })
  return data
}

export async function uploadRequestDocument(requestId, payload) {
  const { data } = await api.post(`/v1/inspection-requests/${requestId}/documents`, payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function uploadRequestDocumentType(requestId, documentType, file) {
  const fd = new FormData()
  fd.append('document_type', documentType)
  fd.append('file', file)
  const { data } = await api.post(`/v1/inspection-requests/${requestId}/documents`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}
