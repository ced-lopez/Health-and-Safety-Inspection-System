import { useEffect, useMemo, useState } from 'react'
import { CalendarClock, CheckCircle2, Eye, ListChecks, Loader2, Search, UserCheck, XCircle } from 'lucide-react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'

import { useAuth } from '@/context/AuthContext'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { fetchInspectionRequests, fetchInspectionRequest, reviewInspectionRequest, assignInspectionRequest, fetchRequestRequirements, confirmPreferredSchedule } from '@/services/inspectionRequestService'
import { fetchPayments } from '@/services/paymentService'
import PaymentSection from '@/components/payments/PaymentSection'
import api from '@/services/api'

const statusLabels = {
  draft: 'Draft',
  submitted: 'Submitted',
  under_review: 'Under Review',
  requirements_incomplete: 'Incomplete',
  approved_for_inspection: 'Approved',
  assigned: 'Assigned',
  inspection_completed: 'Inspection Completed',
  violation_notice_issued: 'Violation Notice',
  follow_up_requested: 'Follow-up Requested',
  clearance_approved: 'Clearance Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}

const statusVariant = (s) => {
  switch (s) {
    case 'approved_for_inspection':
    case 'clearance_approved':
    case 'inspection_completed':
      return 'default'
    case 'rejected':
    case 'requirements_incomplete':
    case 'violation_notice_issued':
    case 'cancelled':
      return 'destructive'
    case 'under_review':
    case 'assigned':
    case 'follow_up_requested':
      return 'secondary'
    default:
      return 'outline'
  }
}

function formatDate(value) {
  if (!value) return 'N/A'
  return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

function categoryName(req) {
  return req?.inspection_category?.name ?? (req?.category ? String(req.category).replace(/_/g, ' ') : 'N/A')
}

const subPathLabels = {
  household: 'Household / Pet Dog Keeping',
  commercial_kennel: 'Commercial Kennel / Breeding',
  backyard_micro_scale: 'Backyard Micro-Scale',
  commercial: 'Commercial',
}

function subPathBadge(req) {
  if (!req?.sub_path) return null
  const variant = req.sub_path === 'commercial' ? 'destructive' : req.sub_path === 'backyard_micro_scale' ? 'secondary' : 'outline'
  return (
    <Badge variant={variant}>
      {subPathLabels[req.sub_path] ?? req.sub_path}
      {req.declared_animal_count ? ` (${req.declared_animal_count} animals)` : ''}
    </Badge>
  )
}

function applicantName(req) {
  return req?.resident?.name ?? req?.applicant_name ?? 'N/A'
}

const terminalStatuses = ['inspection_completed', 'violation_notice_issued', 'follow_up_requested', 'clearance_approved', 'rejected', 'cancelled']

function formatSchedule(value) {
  if (!value) return 'N/A'
  return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function toDateInput(value) {
  if (!value) return ''
  return new Date(value).toLocaleDateString('en-CA')
}

function toTimeInput(value) {
  if (!value) return ''
  const d = new Date(value)
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

function hasConfirmedSchedule(req) {
  return (req?.schedules?.length ?? 0) > 0
}

export default function InspectionRequestsPage() {
  const { user } = useAuth()
  const roleSlug = user?.role?.slug
  const canReview = ['administrator', 'barangay_staff'].includes(roleSlug)

  const [requests, setRequests] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [filters, setFilters] = useState({ search: '', status: 'active', page: 1 })
  const [searchInput, setSearchInput] = useState('')

  const [detailOpen, setDetailOpen] = useState(false)
  const [selectedRequest, setSelectedRequest] = useState(null)
  const [detailLoading, setDetailLoading] = useState(false)

  const [reviewOpen, setReviewOpen] = useState(false)
  const [reviewDecision, setReviewDecision] = useState('under_review')
  const [reviewNotes, setReviewNotes] = useState('')
  const [reviewSubmitting, setReviewSubmitting] = useState(false)

  const [reqOpen, setReqOpen] = useState(false)
  const [requirements, setRequirements] = useState([])
  const [reqSummary, setReqSummary] = useState(null)
  const [reqLoading, setReqLoading] = useState(false)

  const [assignOpen, setAssignOpen] = useState(false)
  const [inspectors, setInspectors] = useState([])
  const [assigneeId, setAssigneeId] = useState('')
  const [assignSubmitting, setAssignSubmitting] = useState(false)

  const [confirmOpen, setConfirmOpen] = useState(false)
  const [confirmForm, setConfirmForm] = useState({ inspector_id: '', date: '', time: '' })
  const [confirmSubmitting, setConfirmSubmitting] = useState(false)

  const [payments, setPayments] = useState([])
  const [feeSchedule, setFeeSchedule] = useState(null)
  const [paymentStatus, setPaymentStatus] = useState(null)
  const [paymentsLoading, setPaymentsLoading] = useState(false)

  const queryParams = useMemo(() => ({
    search: filters.search || undefined,
    status: filters.status !== 'all' ? filters.status : undefined,
    page: filters.page, per_page: 10,
  }), [filters])

  const { data, isError, isLoading, refetch } = useQuery({
    queryKey: ['inspection-requests', queryParams],
    queryFn: () => fetchInspectionRequests(queryParams),
    placeholderData: keepPreviousData,
  })

  useEffect(() => { if (isError) toast.error('Unable to load inspection requests') }, [isError])
  useEffect(() => {
    if (!data) return
    const raw = data.data?.inspection_requests ?? data.data
    setRequests(Array.isArray(raw) ? raw : Array.isArray(raw?.data) ? raw.data : [])
    setMeta(data.data?.meta ?? { current_page: 1, last_page: 1, total: 0 })
  }, [data])
  useEffect(() => {
    const t = setTimeout(() => setFilters((p) => ({ ...p, search: searchInput, page: 1 })), 150)
    return () => clearTimeout(t)
  }, [searchInput])

  const loading = isLoading && requests.length === 0

  async function loadPayments(requestId) {
    setPaymentsLoading(true)
    try {
      const res = await fetchPayments(requestId)
      const d = res.data ?? res
      setPayments(d.payments ?? [])
      setFeeSchedule(d.fee_schedule ?? null)
      setPaymentStatus(d.payment_status ?? null)
    } catch {
      // non-critical
    } finally {
      setPaymentsLoading(false)
    }
  }

  async function openDetail(request) {
    setDetailOpen(true)
    setDetailLoading(true)
    setPayments([])
    setFeeSchedule(null)
    setPaymentStatus(request.payment_status ?? null)
    try {
      const detail = await fetchInspectionRequest(request.id)
      const d = detail.data ?? detail
      setSelectedRequest(d)
      if (d.payment_status) setPaymentStatus(d.payment_status)
      if (d.payments) setPayments(d.payments)
      loadPayments(d.id ?? request.id)
    } catch { toast.error('Unable to load details') }
    finally { setDetailLoading(false) }
  }

  async function refreshDetailAndPayments() {
    if (!selectedRequest?.id) return
    try {
      const detail = await fetchInspectionRequest(selectedRequest.id)
      const d = detail.data ?? detail
      setSelectedRequest(d)
      if (d.payment_status) setPaymentStatus(d.payment_status)
      if (d.payments) setPayments(d.payments)
    } catch { /* ignore */ }
    await loadPayments(selectedRequest.id)
    await refetch()
  }

  function openReview(request, decision) {
    setSelectedRequest(request)
    setReviewDecision(decision)
    setReviewNotes('')
    setReviewOpen(true)
  }

  async function handleReview(event) {
    event.preventDefault()
    setReviewSubmitting(true)
    try {
      await reviewInspectionRequest(selectedRequest.id, { status: reviewDecision, remarks: reviewNotes })
      toast.success(`Request updated`)
      setReviewOpen(false)
      setSelectedRequest((prev) => ({ ...prev, status: reviewDecision, remarks: reviewNotes }))
      await refetch()
    } catch (err) { toast.error(err.response?.data?.message ?? 'Unable to review') }
    finally { setReviewSubmitting(false) }
  }

  async function openRequirements(request) {
    setSelectedRequest(request)
    setReqOpen(true)
    setReqLoading(true)
    try {
      const res = await fetchRequestRequirements(request.id)
      setRequirements(res.data?.requirements ?? [])
      setReqSummary(res.data?.summary ?? null)
    } catch { toast.error('Unable to load requirements') }
    finally { setReqLoading(false) }
  }

  async function reviewFromRequirements(status) {
    if (!selectedRequest) return
    setReviewSubmitting(true)
    try {
      await reviewInspectionRequest(selectedRequest.id, { status, remarks: reviewNotes || undefined })
      toast.success('Request updated')
      setReqOpen(false)
      setReviewNotes('')
      setSelectedRequest((prev) => ({ ...prev, status }))
      await refetch()
    } catch (err) { toast.error(err.response?.data?.message ?? 'Unable to update request') }
    finally { setReviewSubmitting(false) }
  }

  async function openAssign(request) {
    setSelectedRequest(request)
    setAssigneeId('')
    setAssignOpen(true)
    try {
      const res = await api.get('/v1/inspections/options')
      setInspectors(res.data?.data?.inspectors ?? res.data?.inspectors ?? [])
    } catch { toast.error('Unable to load inspectors') }
  }

  async function handleAssign() {
    setAssignSubmitting(true)
    try {
      await assignInspectionRequest(selectedRequest.id, { inspector_id: assigneeId })
      toast.success('Inspector assigned')
      setAssignOpen(false)
      await refetch()
    } catch (err) { toast.error(err.response?.data?.message ?? 'Unable to assign') }
    finally { setAssignSubmitting(false) }
  }

  async function openConfirm(request) {
    setSelectedRequest(request)
    setConfirmForm({
      inspector_id: '',
      date: toDateInput(request.preferred_schedule_at),
      time: toTimeInput(request.preferred_schedule_at),
    })
    setConfirmOpen(true)
    try {
      const res = await api.get('/v1/inspections/options')
      setInspectors(res.data?.data?.inspectors ?? res.data?.inspectors ?? [])
    } catch { toast.error('Unable to load inspectors') }
  }

  async function handleConfirmSchedule() {
    if (!selectedRequest) return
    setConfirmSubmitting(true)
    try {
      const payload = { inspector_id: confirmForm.inspector_id }
      if (confirmForm.date && confirmForm.time) {
        payload.scheduled_date = confirmForm.date
        payload.scheduled_time = confirmForm.time
      }
      await confirmPreferredSchedule(selectedRequest.id, payload)
      toast.success('Inspection schedule confirmed')
      setConfirmOpen(false)
      await refetch()
    } catch (err) {
      toast.error(err.response?.data?.message ?? 'Unable to confirm the schedule')
    } finally {
      setConfirmSubmitting(false)
    }
  }

  function actionButtons(req) {
    if (!canReview) return null
    const buttons = []
    const appPaid = req.payment_status?.application_fee_paid
    const needsPayment = req.status === 'submitted' && !appPaid
    if (req.status === 'submitted') {
      buttons.push(
        <Button key="review" variant="outline" size="sm" onClick={() => openReview(req, 'under_review')} disabled={needsPayment} title={needsPayment ? 'Application fee must be paid before review' : undefined}>
          <CheckCircle2 className="size-4" /> Review {needsPayment ? '(Payment Required)' : ''}
        </Button>
      )
    }
    if (['submitted', 'under_review', 'requirements_incomplete', 'approved_for_inspection'].includes(req.status)) {
      buttons.push(
        <Button key="reqs" variant="outline" size="sm" onClick={() => openRequirements(req)}>
          <ListChecks className="size-4" /> Requirements
        </Button>
      )
    }
    if (['under_review', 'requirements_incomplete', 'approved_for_inspection'].includes(req.status)) {
      buttons.push(
        <Button key="reject" variant="outline" size="sm" onClick={() => openReview(req, 'rejected')}>
          <XCircle className="size-4" /> Reject
        </Button>
      )
    }
    if (req.status === 'approved_for_inspection' && !req.inspection_assignment) {
      buttons.push(
        <Button key="assign" variant="outline" size="sm" onClick={() => openAssign(req)}>
          <UserCheck className="size-4" /> Assign
        </Button>
      )
    }
    if (req.preferred_schedule_at && !terminalStatuses.includes(req.status) && !hasConfirmedSchedule(req)) {
      buttons.push(
        <Button key="confirm" variant="outline" size="sm" onClick={() => openConfirm(req)}>
          <CalendarClock className="size-4" /> Confirm Schedule
        </Button>
      )
    }
    return buttons
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight">Inspection Requests</h2>
        <p className="text-sm text-muted-foreground">Review, verify requirements, approve, reject, and assign inspectors</p>
      </div>

      <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Request Registry</CardTitle>
              <CardDescription>Manage incoming inspection requests from residents</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-3 lg:grid-cols-[1fr_180px]">
                <div className="relative">
                  <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
                  <Input className="pl-8" placeholder="Search by request no., business, or applicant..." value={searchInput} onChange={(e) => setSearchInput(e.target.value)} />
                </div>
                <select className="h-8 rounded-lg border border-input bg-background px-2.5 text-sm" value={filters.status} onChange={(e) => setFilters((p) => ({ ...p, status: e.target.value, page: 1 }))}>
                  <option value="all">All statuses</option>
                  <option value="active">Active review</option>
                  {Object.entries(statusLabels).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </div>

              {loading ? (
                <div className="space-y-3"><Skeleton className="h-10 w-full" /><Skeleton className="h-10 w-full" /></div>
              ) : requests.length === 0 ? (
                <p className="py-8 text-center text-sm text-muted-foreground">No requests found.</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Request No.</TableHead>
                      <TableHead>Applicant</TableHead>
                      <TableHead>Business</TableHead>
                      <TableHead>Category</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Payment</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {requests.map((req) => (
                      <TableRow key={req.id}>
                        <TableCell className="font-mono text-xs">{req.request_number}</TableCell>
                        <TableCell className="font-medium">{applicantName(req)}</TableCell>
                        <TableCell>{req.business_name ?? 'N/A'}</TableCell>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            <span>{categoryName(req)}</span>
                            {subPathBadge(req)}
                          </div>
                        </TableCell>
                        <TableCell><Badge variant={statusVariant(req.status)}>{statusLabels[req.status] ?? req.status}</Badge></TableCell>
                        <TableCell>
                          <div className="flex flex-col gap-1 text-xs">
                            <Badge variant={req.payment_status?.application_fee_paid ? 'default' : 'outline'} className="w-fit">App: {req.payment_status?.application_fee_paid ? 'Paid' : 'Pending'}</Badge>
                            <Badge variant={req.payment_status?.clearance_fee_paid ? 'default' : 'outline'} className="w-fit">Clr: {req.payment_status?.clearance_fee_paid ? 'Paid' : 'Pending'}</Badge>
                          </div>
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end gap-2 flex-wrap">
                            <Button variant="outline" size="sm" onClick={() => openDetail(req)}><Eye className="size-4" /> View</Button>
                            {actionButtons(req)}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}

              <div className="flex items-center justify-between border-t pt-4 text-sm text-muted-foreground">
                <span>{meta.total} requests</span>
                <div className="flex gap-2 items-center">
                  <Button variant="outline" size="sm" disabled={meta.current_page <= 1} onClick={() => setFilters((p) => ({ ...p, page: p.page - 1 }))}>Previous</Button>
                  <span>Page {meta.current_page} of {meta.last_page}</span>
                  <Button variant="outline" size="sm" disabled={meta.current_page >= meta.last_page} onClick={() => setFilters((p) => ({ ...p, page: p.page + 1 }))}>Next</Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

      <Dialog open={detailOpen} onOpenChange={setDetailOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
          <DialogHeader><DialogTitle>Request Details</DialogTitle><DialogDescription>{selectedRequest?.request_number}</DialogDescription></DialogHeader>
          {detailLoading ? <Skeleton className="h-40 w-full" /> : selectedRequest ? (
            <div className="space-y-4">
              <div className="grid gap-3 text-sm sm:grid-cols-2">
                <div><p className="text-xs text-muted-foreground">Status</p><p className="font-medium"><Badge variant={statusVariant(selectedRequest.status)}>{statusLabels[selectedRequest.status] ?? selectedRequest.status}</Badge></p></div>
                <div><p className="text-xs text-muted-foreground">Applicant</p><p className="font-medium">{applicantName(selectedRequest)}</p></div>
                <div><p className="text-xs text-muted-foreground">Business</p><p className="font-medium">{selectedRequest.business_name ?? 'N/A'}</p></div>
                <div><p className="text-xs text-muted-foreground">Category</p><p className="font-medium">{categoryName(selectedRequest)}</p></div>
                <div><p className="text-xs text-muted-foreground">Scale / Sub-path</p><div className="pt-0.5">{subPathBadge(selectedRequest) ?? <span className="font-medium">N/A</span>}</div></div>
                <div><p className="text-xs text-muted-foreground">Application Type</p><p className="font-medium">{selectedRequest.application_type?.name ?? 'N/A'}</p></div>
                <div><p className="text-xs text-muted-foreground">Contact</p><p className="font-medium">{selectedRequest.contact_number ?? 'N/A'} · {selectedRequest.email ?? 'N/A'}</p></div>
                <div className="sm:col-span-2"><p className="text-xs text-muted-foreground">Address</p><p className="font-medium">{selectedRequest.applicant_address ?? 'N/A'}</p></div>
                <div className="sm:col-span-2"><p className="text-xs text-muted-foreground">Purpose / Remarks</p><p className="font-medium">{selectedRequest.remarks ?? 'None'}</p></div>
                <div><p className="text-xs text-muted-foreground">Submitted</p><p className="font-medium">{formatDate(selectedRequest.submitted_at ?? selectedRequest.created_at)}</p></div>
                <div><p className="text-xs text-muted-foreground">Inspector</p><p className="font-medium">{selectedRequest.inspection_assignment?.inspector?.name ?? 'Not assigned'}</p></div>
                {selectedRequest.preferred_schedule_at && (
                  <div>
                    <p className="text-xs text-muted-foreground">Proposed Schedule</p>
                    <p className="font-medium">
                      <Badge variant="secondary">Proposed</Badge>{' '}
                      <span className="text-sm">{formatSchedule(selectedRequest.preferred_schedule_at)}</span>
                    </p>
                  </div>
                )}
                {hasConfirmedSchedule(selectedRequest) && (
                  <div>
                    <p className="text-xs text-muted-foreground">Confirmed Schedule</p>
                    <p className="font-medium">
                      <Badge variant="default">Confirmed</Badge>{' '}
                      <span className="text-sm">{formatSchedule(selectedRequest.schedules[0].scheduled_at)}</span>{' '}
                      {selectedRequest.schedules[0].inspector?.name && <span className="text-xs text-muted-foreground">· {selectedRequest.schedules[0].inspector.name}</span>}
                    </p>
                  </div>
                )}
              </div>

              <div className="space-y-2">
                <p className="text-sm font-medium">Documents ({selectedRequest.documents?.length ?? 0})</p>
                {(selectedRequest.documents?.length ?? 0) === 0 ? (
                  <p className="text-sm text-muted-foreground">No documents uploaded.</p>
                ) : (
                  <div className="space-y-1.5">
                    {selectedRequest.documents.map((doc) => (
                      <div key={doc.id} className="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-sm">
                        <span className="truncate">{doc.original_name ?? doc.file_name}</span>
                        <Badge variant={doc.status === 'verified' ? 'default' : doc.status === 'processed' ? 'secondary' : 'outline'}>{doc.status}</Badge>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              <div className="border-t pt-4">
                <PaymentSection
                  requestId={selectedRequest.id}
                  paymentStatus={paymentStatus ?? selectedRequest.payment_status}
                  payments={payments.length ? payments : (selectedRequest.payments ?? [])}
                  feeSchedule={feeSchedule}
                  loading={paymentsLoading}
                  onRefresh={refreshDetailAndPayments}
                  canRecord={canReview}
                />
              </div>
            </div>
          ) : <p className="text-sm text-muted-foreground text-center py-4">No details.</p>}
        </DialogContent>
      </Dialog>

      <Dialog open={reviewOpen} onOpenChange={setReviewOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader><DialogTitle>Review Request</DialogTitle><DialogDescription>{selectedRequest?.request_number} · {selectedRequest?.business_name}</DialogDescription></DialogHeader>
          <form className="space-y-4" onSubmit={handleReview}>
            <div className="space-y-2">
              <Label>Decision</Label>
              <select className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm" value={reviewDecision} onChange={(e) => setReviewDecision(e.target.value)}>
                <option value="under_review">Start Review</option>
                <option value="approved_for_inspection">Approve for Inspection</option>
                <option value="requirements_incomplete">Return for Incomplete Requirements</option>
                <option value="rejected">Reject</option>
              </select>
            </div>
            <div className="space-y-2">
              <Label>Remarks</Label>
              <textarea className="min-h-20 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                value={reviewNotes} onChange={(e) => setReviewNotes(e.target.value)} placeholder="Optional notes for the resident" />
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setReviewOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={reviewSubmitting}>{reviewSubmitting ? 'Saving...' : 'Submit Review'}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={reqOpen} onOpenChange={setReqOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
          <DialogHeader><DialogTitle>Verify Requirements</DialogTitle><DialogDescription>{selectedRequest?.request_number} · {selectedRequest?.business_name}</DialogDescription></DialogHeader>
          {reqLoading ? (
            <Skeleton className="h-40 w-full" />
          ) : (
            <div className="space-y-4">
              {reqSummary && (
                <div className="grid grid-cols-3 gap-3">
                  <div className="rounded-lg border border-border p-3 text-center">
                    <p className="text-2xl font-semibold">{reqSummary.required_count}</p>
                    <p className="text-xs text-muted-foreground">Required</p>
                  </div>
                  <div className="rounded-lg border border-border p-3 text-center">
                    <p className="text-2xl font-semibold">{reqSummary.uploaded_count}</p>
                    <p className="text-xs text-muted-foreground">Uploaded</p>
                  </div>
                  <div className="rounded-lg border border-border p-3 text-center">
                    <p className="text-2xl font-semibold">{reqSummary.verified_count}</p>
                    <p className="text-xs text-muted-foreground">Verified</p>
                  </div>
                </div>
              )}

              {requirements.length === 0 ? (
                <p className="py-4 text-center text-sm text-muted-foreground">No requirement rules configured for this category/type.</p>
              ) : (
                <div className="space-y-2">
                  {requirements.map((req) => (
                    <div key={req.document_type} className="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-sm">
                      <div className="min-w-0">
                        <p className="truncate font-medium">
                          {req.document_name}
                          {!req.is_required && <span className="ml-2 text-xs text-muted-foreground">Optional</span>}
                        </p>
                        <p className="font-mono text-xs text-muted-foreground">{req.document_type}</p>
                      </div>
                      <div className="flex items-center gap-1.5 shrink-0">
                        {req.expired && <Badge variant="destructive">Expired</Badge>}
                        {!req.uploaded && <Badge variant="outline">Not Uploaded</Badge>}
                        {req.uploaded && !req.verified && <Badge variant="secondary">Uploaded</Badge>}
                        {req.verified && <Badge variant="default">Verified</Badge>}
                      </div>
                    </div>
                  ))}
                </div>
              )}

              <div className="space-y-2">
                <Label>Remarks</Label>
                <textarea className="min-h-16 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  value={reviewNotes} onChange={(e) => setReviewNotes(e.target.value)} placeholder="Notes to the resident (e.g. missing documents)" />
              </div>

              <DialogFooter className="gap-2">
                <Button type="button" variant="outline" onClick={() => setReqOpen(false)}>Cancel</Button>
                <Button type="button" variant="destructive" disabled={reviewSubmitting} onClick={() => reviewFromRequirements('rejected')}>
                  <XCircle className="size-4" /> Reject
                </Button>
                <Button type="button" variant="outline" disabled={reviewSubmitting} onClick={() => reviewFromRequirements('requirements_incomplete')}>
                  Return Incomplete
                </Button>
                <Button type="button" disabled={reviewSubmitting} onClick={() => reviewFromRequirements('approved_for_inspection')}>
                  <CheckCircle2 className="size-4" /> Approve
                </Button>
              </DialogFooter>
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={assignOpen} onOpenChange={setAssignOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader><DialogTitle>Assign Inspector</DialogTitle><DialogDescription>{selectedRequest?.request_number} · {selectedRequest?.business_name}</DialogDescription></DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Select Inspector</Label>
              <select className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm" value={assigneeId} onChange={(e) => setAssigneeId(e.target.value)}>
                <option value="">Choose inspector</option>
                {inspectors.map((insp) => <option key={insp.id} value={insp.id}>{insp.name}</option>)}
              </select>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setAssignOpen(false)}>Cancel</Button>
              <Button onClick={handleAssign} disabled={!assigneeId || assignSubmitting}>{assignSubmitting ? 'Assigning...' : 'Assign'}</Button>
            </DialogFooter>
          </div>
        </DialogContent>
      </Dialog>

      <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader><DialogTitle>Confirm Inspection Schedule</DialogTitle><DialogDescription>{selectedRequest?.request_number} · {selectedRequest?.business_name}</DialogDescription></DialogHeader>
          <div className="space-y-3">
            {selectedRequest?.preferred_schedule_at && (
              <p className="rounded-lg border border-border px-3 py-2 text-sm">
                <span className="text-muted-foreground">Resident's proposal: </span>
                <span className="font-medium">{formatSchedule(selectedRequest.preferred_schedule_at)}</span>
              </p>
            )}
            <div className="space-y-2">
              <Label>Inspector</Label>
              <select className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm" value={confirmForm.inspector_id} onChange={(e) => setConfirmForm((p) => ({ ...p, inspector_id: e.target.value }))}>
                <option value="">Choose inspector</option>
                {inspectors.map((insp) => <option key={insp.id} value={insp.id}>{insp.name}</option>)}
              </select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label>Date</Label>
                <Input type="date" value={confirmForm.date} onChange={(e) => setConfirmForm((p) => ({ ...p, date: e.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label>Time</Label>
                <Input type="time" value={confirmForm.time} onChange={(e) => setConfirmForm((p) => ({ ...p, time: e.target.value }))} />
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setConfirmOpen(false)}>Cancel</Button>
              <Button onClick={handleConfirmSchedule} disabled={!confirmForm.inspector_id || confirmSubmitting}>
                {confirmSubmitting ? <Loader2 className="size-4 animate-spin" /> : <CalendarClock className="size-4" />}
                {confirmSubmitting ? 'Confirming...' : 'Confirm Schedule'}
              </Button>
            </DialogFooter>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
