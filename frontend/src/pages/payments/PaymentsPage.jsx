import { useEffect, useMemo, useState } from 'react'
import { Banknote, Download, Loader2, Search } from 'lucide-react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { confirmPayment, downloadPaymentReceiptGlobal, fetchAllPayments } from '@/services/paymentService'
import { useAuth } from '@/context/AuthContext'

function formatAmount(v) {
  if (v == null) return 'N/A'
  const n = Number(v)
  return Number.isNaN(n) ? String(v) : `PHP ${n.toFixed(2)}`
}
function formatDate(v) {
  if (!v) return 'N/A'
  return new Date(v).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function typeLabel(t) {
  return t === 'application_fee' ? 'Application Fee' : t === 'clearance_fee' ? 'Clearance Fee' : t
}

export default function PaymentsPage() {
  const { user } = useAuth()
  const [searchInput, setSearchInput] = useState('')
  const [filters, setFilters] = useState({ search: '', status: 'all', type: 'all', page: 1 })
  const [downloadingId, setDownloadingId] = useState(null)
  const [confirming, setConfirming] = useState(null)
  const [confirmForm, setConfirmForm] = useState({ amount: '' })
  const [confirmingPayment, setConfirmingPayment] = useState(false)
  const canConfirmPayments = ['administrator', 'barangay_staff'].includes(user?.role?.slug)

  useEffect(() => {
    const t = setTimeout(() => setFilters((p) => ({ ...p, search: searchInput, page: 1 })), 200)
    return () => clearTimeout(t)
  }, [searchInput])

  const queryParams = useMemo(() => ({
    search: filters.search || undefined,
    status: filters.status !== 'all' ? filters.status : undefined,
    type: filters.type !== 'all' ? filters.type : undefined,
    page: filters.page,
    per_page: 15,
  }), [filters])

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['payments', queryParams],
    queryFn: () => fetchAllPayments(queryParams),
    placeholderData: keepPreviousData,
  })

  useEffect(() => { if (isError) toast.error('Unable to load payments') }, [isError])

  const payments = data?.data?.payments ?? []
  const meta = data?.data?.meta ?? { current_page: 1, last_page: 1, total: 0 }

  async function handleDownload(payment) {
    setDownloadingId(payment.id)
    try {
      const blob = await downloadPaymentReceiptGlobal(payment.id)
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      const reqNo = payment.inspection_request?.request_number ?? payment.inspection_request_id
      a.download = `receipt-${reqNo}-${payment.type}.pdf`
      document.body.appendChild(a)
      a.click()
      a.remove()
      URL.revokeObjectURL(url)
    } catch {
      toast.error('Unable to download receipt')
    } finally {
      setDownloadingId(null)
    }
  }

  function openConfirm(payment) {
    setConfirming(payment)
    setConfirmForm({ amount: String(payment.amount ?? '') })
  }

  async function handleConfirm(event) {
    event.preventDefault()
    if (!confirming) return

    setConfirmingPayment(true)
    try {
      await confirmPayment(confirming.id, {
        amount: Number(confirmForm.amount),
      })
      toast.success('Payment confirmed. The related workflow has been updated.')
      setConfirming(null)
      await refetch()
    } catch (error) {
      toast.error(error.response?.data?.message ?? 'Unable to confirm payment')
    } finally {
      setConfirmingPayment(false)
    }
  }

  const loading = isLoading && payments.length === 0

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight flex items-center gap-2">
          <Banknote className="size-6" /> Payments
        </h2>
        <p className="text-sm text-muted-foreground">Over-the-counter payments — application & clearance fees, receipts, and OR numbers</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Payment Registry</CardTitle>
          <CardDescription>All confirmed and pending payments. Online payment is <Badge variant="secondary" className="text-[10px]">Coming Soon</Badge> — manual only.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 lg:grid-cols-[1fr_160px_160px]">
            <div className="relative">
              <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
              <Input className="pl-8" placeholder="Search OR, request no., business or applicant..." value={searchInput} onChange={(e) => setSearchInput(e.target.value)} />
            </div>
            <select className="h-8 rounded-lg border border-input bg-background px-2.5 text-sm" value={filters.type} onChange={(e) => setFilters((p) => ({ ...p, type: e.target.value, page: 1 }))}>
              <option value="all">All types</option>
              <option value="application_fee">Application Fee</option>
              <option value="clearance_fee">Clearance Fee</option>
            </select>
            <select className="h-8 rounded-lg border border-input bg-background px-2.5 text-sm" value={filters.status} onChange={(e) => setFilters((p) => ({ ...p, status: e.target.value, page: 1 }))}>
              <option value="all">All statuses</option>
              <option value="paid">Paid</option>
              <option value="pending">Pending</option>
            </select>
          </div>

          {loading ? (
            <div className="space-y-3"><Skeleton className="h-10 w-full" /><Skeleton className="h-10 w-full" /></div>
          ) : payments.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">No payments found. Record a payment from an inspection request detail.</p>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>OR / Ref</TableHead>
                    <TableHead>Request</TableHead>
                    <TableHead>Business / Applicant</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Paid At</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {payments.map((p) => (
                    <TableRow key={p.id}>
                      <TableCell className="font-mono text-xs">{p.or_number ?? '—'}</TableCell>
                      <TableCell className="font-mono text-xs">{p.inspection_request?.request_number ?? `#${p.inspection_request_id}`}</TableCell>
                      <TableCell>
                        <div className="font-medium text-sm truncate max-w-[180px]">{p.inspection_request?.business_name ?? p.inspection_request?.applicant_name ?? 'N/A'}</div>
                        <div className="text-xs text-muted-foreground truncate">{p.inspection_request?.inspection_category?.name ?? ''}</div>
                      </TableCell>
                      <TableCell><Badge variant="outline" className="text-xs">{typeLabel(p.type)}</Badge></TableCell>
                      <TableCell>{formatAmount(p.amount)}</TableCell>
                      <TableCell><Badge variant={p.status === 'paid' ? 'default' : 'secondary'}>{p.status}</Badge></TableCell>
                      <TableCell className="text-xs">{formatDate(p.paid_at)}</TableCell>
                      <TableCell className="text-right">
                        {p.status === 'paid' ? (
                          <Button variant="outline" size="icon-sm" onClick={() => handleDownload(p)} disabled={downloadingId === p.id} aria-label="Download receipt">
                            {downloadingId === p.id ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />}
                          </Button>
                        ) : canConfirmPayments ? (
                          <Button variant="default" size="sm" onClick={() => openConfirm(p)}>Confirm Payment</Button>
                        ) : (
                          <span className="text-xs text-muted-foreground">Pending</span>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}

          <div className="flex items-center justify-between border-t pt-4 text-sm text-muted-foreground">
            <span>{meta.total} payments</span>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" disabled={meta.current_page <= 1} onClick={() => setFilters((p) => ({ ...p, page: p.page - 1 }))}>Previous</Button>
              <span>Page {meta.current_page} of {meta.last_page}</span>
              <Button variant="outline" size="sm" disabled={meta.current_page >= meta.last_page} onClick={() => setFilters((p) => ({ ...p, page: p.page + 1 }))}>Next</Button>
              <Button variant="outline" size="sm" onClick={() => refetch()}>Refresh</Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Dialog open={Boolean(confirming)} onOpenChange={(open) => !open && setConfirming(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Confirm Payment</DialogTitle>
            <DialogDescription>
              Confirm receipt of this {confirming ? typeLabel(confirming.type).toLowerCase() : 'payment'}. The system will assign its OR number automatically.
            </DialogDescription>
          </DialogHeader>
          <form className="space-y-4" onSubmit={handleConfirm}>
            <div className="space-y-2">
              <Label htmlFor="payment-amount">Amount (PHP)</Label>
              <Input id="payment-amount" type="number" min="0" step="0.01" value={confirmForm.amount} onChange={(e) => setConfirmForm((form) => ({ ...form, amount: e.target.value }))} required />
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setConfirming(null)}>Cancel</Button>
              <Button type="submit" disabled={confirmingPayment}>{confirmingPayment ? 'Confirming…' : 'Confirm Payment'}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
