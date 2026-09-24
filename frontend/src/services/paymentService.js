import api from '@/services/api'

export async function fetchAllPayments(params) {
  const { data } = await api.get('/v1/payments', { params })
  return data
}

export async function fetchPayments(requestId) {
  const { data } = await api.get(`/v1/inspection-requests/${requestId}/payments`)
  return data
}

export async function recordPayment(requestId, payload) {
  const { data } = await api.post(`/v1/inspection-requests/${requestId}/payments`, payload)
  return data
}

export async function confirmPayment(paymentId, payload) {
  const { data } = await api.patch(`/v1/payments/${paymentId}/confirm`, payload)
  return data
}

export function paymentReceiptUrl(requestId, paymentId) {
  return `/v1/inspection-requests/${requestId}/payments/${paymentId}/receipt`
}

export async function downloadPaymentReceipt(requestId, paymentId) {
  const { data } = await api.get(paymentReceiptUrl(requestId, paymentId), {
    responseType: 'blob',
  })
  return data
}

export async function downloadPaymentReceiptGlobal(paymentId) {
  const { data } = await api.get(`/v1/payments/${paymentId}/receipt-global`, {
    responseType: 'blob',
  })
  return data
}
