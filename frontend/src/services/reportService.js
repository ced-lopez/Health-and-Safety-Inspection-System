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
