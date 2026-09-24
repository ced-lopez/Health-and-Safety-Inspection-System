import api from '@/services/api'

export async function fetchInspectionReports(params) {
  const { data } = await api.get('/v1/reports/inspections', { params })
  return data
}

export async function fetchViolationReports(params) {
  const { data } = await api.get('/v1/reports/violations', { params })
  return data
}

export async function fetchClearanceReports(params) {
  const { data } = await api.get('/v1/reports/clearances', { params })
  return data
}

export async function fetchDashboardReport(params) {
  const { data } = await api.get('/v1/reports/dashboard', { params })
  return data
}

export async function fetchSobaReports(year, semester) {
  const { data } = await api.get('/v1/reports/soba', { params: { year, semester } })
  return data
}

export async function exportReport(type, params = {}, format = 'csv') {
  const res = await api.get(`/v1/reports/${type}/export`, {
    params: { ...params, format },
    responseType: 'blob',
  })
  const disposition = res.headers['content-disposition'] || res.headers['Content-Disposition']
  let filename = `${type}.${format === 'excel' ? 'xlsx' : format}`
  if (disposition) {
    const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
    if (match?.[1]) filename = match[1].replace(/['"]/g, '').trim()
  }
  const blob = new Blob([res.data], { type: res.headers['content-type'] })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
  return filename
}
