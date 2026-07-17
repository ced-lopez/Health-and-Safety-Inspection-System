import api from '@/services/api'

export async function fetchInspectionOptions() {
  const { data } = await api.get('/v1/inspections/options')
  return data
}

export async function fetchInspectionSchedules(params) {
  const { data } = await api.get('/v1/inspections/schedules', { params })
  return data
}

export async function createInspectionSchedule(payload) {
  const { data } = await api.post('/v1/inspections/schedules', payload)
  return data
}

export async function updateInspectionSchedule(id, payload) {
  const { data } = await api.put(`/v1/inspections/schedules/${id}`, payload)
  return data
}

export async function deleteInspectionSchedule(id) {
  const { data } = await api.delete(`/v1/inspections/schedules/${id}`)
  return data
}

export async function fetchComplianceChecklist(scheduleId) {
  const { data } = await api.get(`/v1/inspections/schedules/${scheduleId}/checklist`)
  return data
}

export async function saveComplianceChecklist(scheduleId, payload) {
  const { data } = await api.post(
    `/v1/inspections/schedules/${scheduleId}/checklist`,
    payload,
    {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    },
  )

  return data
}

export async function fetchInspectionReport(scheduleId) {
  const { data } = await api.get(`/v1/inspections/schedules/${scheduleId}/report`)
  return data
}

export async function updateInspectionReport(scheduleId, payload) {
  const { data } = await api.put(
    `/v1/inspections/schedules/${scheduleId}/report`,
    payload,
  )
  return data
}
