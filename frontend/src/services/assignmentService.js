import api from '@/services/api'

export async function fetchAssignments(params) {
  const { data } = await api.get('/v1/inspection-assignments', { params })
  return data
}

export async function fetchAssignment(id) {
  const { data } = await api.get(`/v1/inspection-assignments/${id}`)
  return data
}

export async function startAssignment(id) {
  const { data } = await api.put(`/v1/inspection-assignments/${id}/start`)
  return data
}

export async function submitAssignment(id, payload) {
  const { data } = await api.put(`/v1/inspection-assignments/${id}/submit`, payload)
  return data
}

export async function fetchAssignmentChecklist(id) {
  const { data } = await api.get(`/v1/inspection-assignments/${id}/checklist`)
  return data
}

export async function saveAssignmentChecklist(id, payload) {
  const { data } = await api.post(
    `/v1/inspection-assignments/${id}/checklist`,
    payload,
    {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    },
  )

  return data
}

export async function fetchAssignmentReport(id) {
  const { data } = await api.get(`/v1/inspection-assignments/${id}/report`)
  return data
}

export async function updateAssignmentReport(id, payload) {
  const { data } = await api.put(`/v1/inspection-assignments/${id}/report`, payload)
  return data
}
