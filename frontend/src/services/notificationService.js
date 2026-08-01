import api from '@/services/api'

export async function fetchNotifications(params) {
  const { data } = await api.get('/v1/notifications', { params })
  return data
}

export async function markNotificationRead(id) {
  const { data } = await api.put(`/v1/notifications/${id}/read`)
  return data
}

export async function markAllNotificationsRead() {
  const { data } = await api.put('/v1/notifications/read-all')
  return data
}

export async function deleteNotification(id) {
  const { data } = await api.delete(`/v1/notifications/${id}`)
  return data
}
