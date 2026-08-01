import api from '@/services/api'

export async function fetchAuditLogs(params) {
  const { data } = await api.get('/v1/audit-logs', { params })
  return data
}

export async function fetchAuditLogFilters() {
  const { data } = await api.get('/v1/audit-logs/filters')
  return data
}

export async function exportAuditLogs(params) {
  const { data } = await api.get('/v1/audit-logs/export', { params, responseType: 'blob' })
  return data
}

export async function archiveAuditLogs(payload) {
  const { data } = await api.post('/v1/audit-logs/archive', payload)
  return data
}
