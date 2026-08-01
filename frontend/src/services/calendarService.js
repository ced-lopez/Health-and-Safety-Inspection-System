import api from '@/services/api'

export async function fetchCalendar(params) {
  const { data } = await api.get('/v1/inspections/calendar', { params })
  return data
}

export async function scheduleInspection(id, payload) {
  const { data } = await api.patch(`/v1/inspections/schedules/${id}/schedule`, payload)
  return data
}

export async function fetchInspectorAvailability(inspectorId, params) {
  const { data } = await api.get(`/v1/inspectors/${inspectorId}/availability`, { params })
  return data
}
