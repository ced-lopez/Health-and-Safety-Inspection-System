import { useState } from 'react'
import { Banknote, CreditCard, Download, Loader2, Receipt } from 'lucide-react'
import { toast } from 'sonner'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { confirmPayment, downloadPaymentReceipt, recordPayment } from '@/services/paymentService'
import api from '@/services/api'

function formatAmount(value) {
  if (value == null || value === '') return 'N/A'
  const num = Number(value)
  if (Number.isNaN(num)) return String(value)
  return `PHP ${num.toFixed(2)}`
}

function formatDateTime(value) {
  if (!value) return 'N/A'
  return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function typeLabel(type) {
  if (type === 'application_fee') return 'Application Fee'
  if (type === 'clearance_fee') return 'Clearance Fee'
  return type
}

export default function PaymentSection({
  requestId,
  paymentStatus,
  payments,
  feeSchedule,
  loading,
  onRefresh,
  canRecord = false,
}) {
  const [formOpen, setFormOpen] = useState(false)
  const [formType, setFormType] = useState('application_fee')
  const [form, setForm] = useState({ amount: '', method: 'manual' })
  const [submitting, setSubmitting] = useState(false)
  const [downloadingId, setDownloadingId] = useState(null)
  const [confirming, setConfirming] = useState(null)
  const [confirmForm, setConfirmForm] = useState({ amount: '' })

  function openForm(type) {
    const suggested = feeSchedule?.[type] ?? ''
    setFormType(type)
    setForm({ amount: suggested !== '' ? String(suggested) : '', method: 'manual' })
    setFormOpen(true)
  }

  async function handleSubmit(e) {
    e.preventDefault()
    if (form.method === 'online') {
      toast.error('Online payment is coming soon. Use Manual instead.')
      return
    }
    setSubmitting(true)
    try {
      const payload = {
        type: formType,
        method: 'manual',
      }
      if (form.amount) payload.amount = Number(form.amount)
      if (form.paid_at) payload.paid_at = form.paid_at
      await recordPayment(requestId, payload)
      toast.success(`${typeLabel(formType)} recorded; confirm it with the OR number`)
      setFormOpen(false)
      onRefresh?.()
    } catch (err) {
      toast.error(err.response?.data?.message ?? 'Unable to record payment')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleConfirm(e) {
    e.preventDefault()
    setSubmitting(true)
    try {
      await confirmPayment(confirming.id, {
        amount: Number(confirmForm.amount),
      })
      toast.success('Payment confirmed and OR number generated')
      setConfirming(null)
      onRefresh?.()
    } catch (err) {
      toast.error(err.response?.data?.message ?? 'Unable to confirm payment')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleDownload(payment) {
    setDownloadingId(payment.id)
    try {
      const blob = await downloadPaymentReceipt(requestId, payment.id)
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      // Try to infer filename from content-disposition or fallback
      a.download = `receipt-${requestId}-${payment.type}.pdf`
      document.body.appendChild(a)
      a.click()
      a.remove()
      URL.revokeObjectURL(url)
    } catch {
      // Fallback: open via api url (will stream with auth)
      const token = localStorage.getItem('auth_token')
      const base = api.defaults.baseURL || ''
      const url = `${base}/v1/inspection-requests/${requestId}/payments/${payment.id}/receipt`
      window.open(url + (token ? `?token=${encodeURIComponent(token)}` : ''), '_blank')
      toast.error('Direct download failed, opened in new tab')
    } finally {
      setDownloadingId(null)
    }
  }

  const appPaid = paymentStatus?.application_fee_paid
  const clrPaid = paymentStatus?.clearance_fee_paid

  if (loading) {
    return <Skeleton className="h-40 w-full" />
  }

  return (
    <>
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h4 className="text-sm font-semibold flex items-center gap-2">
            <Banknote className="size-4" /> Payments
          </h4>
          {canRecord && (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={() => openForm('application_fee')} disabled={appPaid}>
                <Receipt className="size-4" /> Record Application Fee
              </Button>
              <Button variant="outline" size="sm" onClick={() => openForm('clearance_fee')} disabled={clrPaid}>
                <Receipt className="size-4" /> Record Clearance Fee
              </Button>
            </div>
          )}
        </div>

        {feeSchedule && (
          <div className="grid grid-cols-2 gap-3 text-sm">
            <div className="rounded-lg border border-border p-3">
              <p className="text-xs text-muted-foreground">Application Fee (this category)</p>
              <p className="font-semibold">{formatAmount(feeSchedule.application_fee)}</p>
              <Badge variant={appPaid ? 'default' : 'outline'} className="mt-1">{appPaid ? 'Paid' : 'Pending'}</Badge>
            </div>
            <div className="rounded-lg border border-border p-3">
              <p className="text-xs text-muted-foreground">Clearance Fee (this category)</p>
              <p className="font-semibold">{formatAmount(feeSchedule.clearance_fee)}</p>
              <Badge variant={clrPaid ? 'default' : 'outline'} className="mt-1">{clrPaid ? 'Paid' : 'Pending'}</Badge>
            </div>
          </div>
        )}

        {!appPaid && (
          <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
            Application fee must be confirmed paid before this request can move past <strong>Submitted</strong>.
          </p>
        )}
        {!clrPaid && (
          <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
            Clearance / QR generation is blocked until the <strong>clearance fee</strong> is confirmed paid (created as pending after inspection completion).
          </p>
        )}

        <div className="space-y-2">
          <p className="text-xs font-medium text-muted-foreground">History ({payments?.length ?? 0})</p>
          {(payments?.length ?? 0) === 0 ? (
            <p className="text-sm text-muted-foreground">No payments recorded yet.</p>
          ) : (
            <div className="space-y-2">
              {payments.map((p) => (
                <div key={p.id} className="flex items-center justify-between rounded-lg border border-border px-3 py-2">
                  <div className="min-w-0">
                    <p className="text-sm font-medium truncate">{typeLabel(p.type)} · {formatAmount(p.amount)}</p>
                    <p className="text-xs text-muted-foreground truncate">
                      {p.or_number ? `OR ${p.or_number} · ` : ''}{p.method === 'manual' ? 'Over-the-counter' : p.method} · {p.status === 'paid' ? formatDateTime(p.paid_at) : 'Pending'}
                      {p.confirmed_by ? ` · by ${p.confirmed_by.name}` : ''}
                    </p>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    <Badge variant={p.status === 'paid' ? 'default' : 'secondary'}>{p.status}</Badge>
                    {canRecord && p.status === 'pending' && (
                      <Button variant="outline" size="sm" onClick={() => {
                        setConfirming(p)
                        setConfirmForm({ amount: String(p.amount ?? '') })
                      }}>Confirm Payment</Button>
                    )}
                    {p.status === 'paid' && (
                      <Button variant="outline" size="icon-sm" onClick={() => handleDownload(p)} disabled={downloadingId === p.id} title="Download receipt">
                        {downloadingId === p.id ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />}
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        <p className="text-[11px] text-muted-foreground flex items-center gap-1">
          <CreditCard className="size-3" /> Online payment <Badge variant="outline" className="ml-1 text-[10px]">Coming Soon</Badge> — over-the-counter only for now.
        </p>
      </div>

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Record {typeLabel(formType)}</DialogTitle>
            <DialogDescription>Record a pending over-the-counter payment. A separate OR-number confirmation is required before it is paid.</DialogDescription>
          </DialogHeader>
          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="space-y-2">
              <Label>Payment Method</Label>
              <div className="grid grid-cols-2 gap-2">
                <label className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm cursor-pointer ${form.method === 'manual' ? 'border-primary bg-primary/5' : 'border-border'}`}>
                  <input type="radio" name="method" value="manual" checked={form.method === 'manual'} onChange={() => setForm((f) => ({ ...f, method: 'manual' }))} />
                  Manual (OTC)
                </label>
                <label className="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm opacity-60 cursor-not-allowed">
                  <input type="radio" value="online" disabled checked={form.method === 'online'} onChange={() => setForm((f) => ({ ...f, method: 'online' }))} />
                  Online <Badge variant="secondary" className="text-[10px]">Coming Soon</Badge>
                </label>
              </div>
              <p className="text-[11px] text-muted-foreground">Online gateway is not yet integrated — coming soon.</p>
            </div>

            <div className="space-y-2">
              <Label htmlFor="pay-amount">Amount (PHP)</Label>
              <Input id="pay-amount" type="number" step="0.01" min="0" value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))} placeholder={feeSchedule?.[formType] != null ? String(feeSchedule[formType]) : 'e.g. 150.00'} />
              <p className="text-[11px] text-muted-foreground">Leave blank to use the default fee for this category.</p>
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={submitting || form.method === 'online'}>{submitting ? <><Loader2 className="size-4 animate-spin" /> Saving...</> : 'Record Payment'}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={!!confirming} onOpenChange={(open) => !open && setConfirming(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Confirm Payment</DialogTitle>
            <DialogDescription>{confirming && `${typeLabel(confirming.type)} — this action records the official receipt and unlocks its gate.`}</DialogDescription>
          </DialogHeader>
          <form className="space-y-4" onSubmit={handleConfirm}>
            <p className="rounded-md bg-muted p-3 text-sm text-muted-foreground">An OR number will be generated automatically when payment is confirmed.</p>
            <div className="space-y-2"><Label htmlFor="confirm-amount">Amount (PHP)</Label><Input id="confirm-amount" type="number" min="0" step="0.01" value={confirmForm.amount} onChange={(e) => setConfirmForm((f) => ({ ...f, amount: e.target.value }))} required /></div>
            <DialogFooter><Button type="button" variant="outline" onClick={() => setConfirming(null)}>Cancel</Button><Button type="submit" disabled={submitting}>{submitting ? 'Confirming...' : 'Confirm Payment'}</Button></DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  )
}
