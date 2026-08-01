import { useEffect, useMemo, useState } from 'react'
import { Download, Eye, FileSearch, FileText, Maximize2, Minimize2, Search } from 'lucide-react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { fetchInspectionRequests, fetchInspectionRequest, fetchRequestDocuments, fetchRequestDocumentFile, uploadRequestDocument } from '@/services/inspectionRequestService'

const statusLabels = {
  draft: 'Draft',
  submitted: 'Submitted',
  under_review: 'Under Review',
  requirements_incomplete: 'Requirements Incomplete',
  approved_for_inspection: 'Approved for Inspection',
  assigned: 'Assigned',
  inspection_completed: 'Inspection Completed',
  violation_notice_issued: 'Violation Notice Issued',
  follow_up_requested: 'Follow-up Requested',
  clearance_approved: 'Clearance Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}

const statusVariant = (s) => {
  switch (s) {
    case 'rejected':
    case 'cancelled':
    case 'violation_notice_issued':
      return 'destructive'
    case 'under_review':
    case 'requirements_incomplete':
      return 'secondary'
    case 'approved_for_inspection':
    case 'assigned':
    case 'inspection_completed':
    case 'follow_up_requested':
    case 'clearance_approved':
    default:
      return 'outline'
  }
}

function formatDate(value) {
  if (!value) return 'N/A'
  return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
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

export default function MyApplicationsPage() {
  const [requests, setRequests] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [searchInput, setSearchInput] = useState('')
  const [filters, setFilters] = useState({ search: '', page: 1 })
  const [detailOpen, setDetailOpen] = useState(false)
  const [selectedRequest, setSelectedRequest] = useState(null)
  const [documents, setDocuments] = useState([])
  const [detailLoading, setDetailLoading] = useState(false)
  const [uploadingDoc, setUploadingDoc] = useState(false)
  const [previewOpen, setPreviewOpen] = useState(false)
  const [previewUrl, setPreviewUrl] = useState(null)
  const [previewMime, setPreviewMime] = useState('')
  const [previewName, setPreviewName] = useState('')
  const [previewLoading, setPreviewLoading] = useState(false)
  const [previewMaximized, setPreviewMaximized] = useState(false)

  const queryParams = useMemo(() => ({
    search: filters.search || undefined,
    page: filters.page,
    per_page: 10,
  }), [filters])

  const { data, isError, isLoading } = useQuery({
    queryKey: ['my-inspection-requests', queryParams],
    queryFn: () => fetchInspectionRequests(queryParams),
    placeholderData: keepPreviousData,
  })

  useEffect(() => {
    if (isError) { toast.error('Unable to load applications'); }
  }, [isError])

  useEffect(() => {
    if (!data) return
    const registry = data.data?.inspection_requests ?? data.data ?? []
    setRequests(Array.isArray(registry) ? registry : registry.data ?? [])
    setMeta(data.data?.meta ?? { current_page: 1, last_page: 1, total: 0 })
  }, [data])

  useEffect(() => {
    const t = setTimeout(() => setFilters((prev) => ({ ...prev, search: searchInput, page: 1 })), 150)
    return () => clearTimeout(t)
  }, [searchInput])

  const loading = isLoading && requests.length === 0

  async function openDetail(request) {
    setSelectedRequest(request)
    setDetailOpen(true)
    setDetailLoading(true)
    try {
      const [detailRes, docRes] = await Promise.all([
        fetchInspectionRequest(request.id),
        fetchRequestDocuments(request.id),
      ])
      setSelectedRequest(detailRes.data ?? detailRes)
      setDocuments(docRes.data?.documents ?? docRes ?? [])
    } catch { toast.error('Unable to load application details') } finally {
      setDetailLoading(false)
    }
  }

  async function handleUploadDocument(event) {
    if (!selectedRequest || !event.target.files?.length) return
    setUploadingDoc(true)
    try {
      const fd = new FormData()
      Array.from(event.target.files).forEach((f) => fd.append('documents[]', f))
      await uploadRequestDocument(selectedRequest.id, fd)
      toast.success('Document uploaded')
      const docRes = await fetchRequestDocuments(selectedRequest.id)
      setDocuments(docRes.data?.documents ?? docRes ?? [])
    } catch { toast.error('Upload failed') }
    finally { setUploadingDoc(false); event.target.value = '' }
  }

  async function openPreview(doc) {
    setPreviewOpen(true)
    setPreviewLoading(true)
    setPreviewUrl(null)
    setPreviewMaximized(false)
    setPreviewName(doc.original_name ?? doc.file_name ?? 'document')
    try {
      const blob = await fetchRequestDocumentFile(selectedRequest.id, doc.id)
      setPreviewMime(blob.type || doc.mime_type || 'application/octet-stream')
      setPreviewUrl(URL.createObjectURL(blob))
    } catch {
      toast.error('Unable to load document file')
      setPreviewOpen(false)
    } finally {
      setPreviewLoading(false)
    }
  }

  function closePreview() {
    if (previewUrl) URL.revokeObjectURL(previewUrl)
    setPreviewUrl(null)
    setPreviewOpen(false)
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight">My Applications</h2>
        <p className="text-sm text-muted-foreground">Track your inspection requests and upload documents</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Inspection Requests</CardTitle>
          <CardDescription>All your submitted requests</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="relative max-w-sm">
            <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
            <Input className="pl-8" placeholder="Search by business name..." value={searchInput} onChange={(e) => setSearchInput(e.target.value)} />
          </div>

          {loading ? (
            <div className="space-y-3"><Skeleton className="h-10 w-full" /><Skeleton className="h-10 w-full" /></div>
          ) : requests.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">No applications found.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Business</TableHead>
                  <TableHead>Category</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {requests.map((req) => (
                  <TableRow key={req.id}>
                    <TableCell className="font-medium">{req.business_name || req.applicant_name || 'N/A'}</TableCell>
                    <TableCell>
                      <div className="flex flex-col gap-1">
                        <span>{req.inspection_category?.name ?? 'N/A'}</span>
                        {subPathBadge(req)}
                      </div>
                    </TableCell>
                    <TableCell>{formatDate(req.created_at ?? req.submitted_at)}</TableCell>
                    <TableCell>
                      <Badge variant={statusVariant(req.status)}>{statusLabels[req.status] ?? req.status}</Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="outline" size="icon-sm" onClick={() => openDetail(req)}>
                        <Eye className="size-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}

          <div className="flex items-center justify-between border-t pt-4 text-sm text-muted-foreground">
            <span>{meta.total} requests</span>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" disabled={meta.current_page <= 1} onClick={() => setFilters((p) => ({ ...p, page: p.page - 1 }))}>Previous</Button>
              <span className="self-center">Page {meta.current_page} of {meta.last_page}</span>
              <Button variant="outline" size="sm" disabled={meta.current_page >= meta.last_page} onClick={() => setFilters((p) => ({ ...p, page: p.page + 1 }))}>Next</Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Dialog open={detailOpen} onOpenChange={setDetailOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>Application Details</DialogTitle>
            <DialogDescription>{selectedRequest?.business_name ?? 'Request'} — {statusLabels[selectedRequest?.status] ?? selectedRequest?.status}</DialogDescription>
          </DialogHeader>

          {detailLoading ? (
            <div className="space-y-3"><Skeleton className="h-8 w-full" /><Skeleton className="h-8 w-full" /></div>
          ) : selectedRequest ? (
            <div className="space-y-4">
              <div className="grid gap-3 text-sm sm:grid-cols-2">
                <div><p className="text-xs text-muted-foreground">Business Name</p><p className="font-medium">{selectedRequest.business_name ?? 'N/A'}</p></div>
                <div><p className="text-xs text-muted-foreground">Category</p><p className="font-medium">{selectedRequest.inspection_category?.name ?? 'N/A'}</p></div>
                <div><p className="text-xs text-muted-foreground">Scale / Sub-path</p><div className="pt-0.5">{subPathBadge(selectedRequest) ?? <span className="font-medium">N/A</span>}</div></div>
                <div><p className="text-xs text-muted-foreground">Application Type</p><p className="font-medium">{selectedRequest.application_type?.name ?? 'N/A'}</p></div>
                <div><p className="text-xs text-muted-foreground">Status</p><p className="font-medium"><Badge variant={statusVariant(selectedRequest.status)}>{statusLabels[selectedRequest.status] ?? selectedRequest.status}</Badge></p></div>
                <div className="sm:col-span-2"><p className="text-xs text-muted-foreground">Address</p><p className="font-medium">{selectedRequest.applicant_address ?? 'N/A'}</p></div>
                <div className="sm:col-span-2"><p className="text-xs text-muted-foreground">Notes / Remarks</p><p className="font-medium">{selectedRequest.remarks ?? 'None'}</p></div>
              </div>

              <div className="border-t pt-4">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-sm font-semibold">Uploaded Documents</h4>
                  <label className="cursor-pointer text-xs text-primary hover:underline">
                    <input type="file" multiple accept="image/*,.pdf" className="hidden" onChange={handleUploadDocument} disabled={uploadingDoc} />
                    + Add Document
                  </label>
                </div>
                {documents.length === 0 ? (
                  <p className="text-sm text-muted-foreground">No documents uploaded yet.</p>
                ) : (
                  <div className="space-y-2">
                    {Array.isArray(documents) && documents.map((doc) => (
                      <div key={doc.id} className="flex items-center justify-between rounded-lg border border-border p-2.5">
                        <div className="flex items-center gap-2 min-w-0">
                          <FileText className="size-4 shrink-0 text-muted-foreground" />
                          <span className="text-sm truncate">{doc.file_name ?? doc.filename ?? 'Document'}</span>
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                          <Badge variant="outline" className="shrink-0">{doc.status ?? 'pending'}</Badge>
                          <Button variant="outline" size="icon-sm" onClick={() => openPreview(doc)} title="View file">
                            <FileSearch className="size-4" />
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground text-center py-4">No details available.</p>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={previewOpen} onOpenChange={(open) => { if (!open) closePreview() }}>
        <DialogContent
          className={previewMaximized
            ? 'h-[94vh] max-h-[94vh] w-[96vw] max-w-[96vw] overflow-hidden'
            : 'max-h-[90vh] overflow-hidden sm:max-w-3xl'}
        >
          <button
            type="button"
            onClick={() => setPreviewMaximized((m) => !m)}
            className="absolute top-2 right-11 inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            title={previewMaximized ? 'Minimize' : 'Maximize'}
          >
            {previewMaximized ? <Minimize2 className="size-4" /> : <Maximize2 className="size-4" />}
            <span className="sr-only">{previewMaximized ? 'Minimize' : 'Maximize'}</span>
          </button>
          <DialogHeader>
            <DialogTitle>Document Preview</DialogTitle>
            <DialogDescription className="truncate">{previewName}</DialogDescription>
          </DialogHeader>
          {previewLoading ? (
            <div className="space-y-3"><Skeleton className="h-64 w-full" /></div>
          ) : previewUrl ? (
            <div className="overflow-auto rounded-lg border border-border">
              {previewMime.startsWith('image/') ? (
                <img
                  src={previewUrl}
                  alt={previewName}
                  className={`mx-auto w-auto object-contain ${previewMaximized ? 'max-h-[calc(100vh-11rem)]' : 'max-h-[70vh]'}`}
                />
              ) : previewMime === 'application/pdf' ? (
                <iframe src={previewUrl} title={previewName} className={`w-full ${previewMaximized ? 'h-[calc(100vh-11rem)]' : 'h-[70vh]'}`} />
              ) : (
                <div className="flex flex-col items-center gap-3 p-10 text-sm text-muted-foreground">
                  <FileText className="size-10" />
                  <p>Preview not available for this file type.</p>
                </div>
              )}
            </div>
          ) : (
            <p className="py-8 text-center text-sm text-muted-foreground">Unable to load the document.</p>
          )}
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={() => { if (previewUrl) { const a = document.createElement('a'); a.href = previewUrl; a.download = previewName; a.click(); } }}>
              <Download className="size-4" />
              Download
            </Button>
            <Button variant="outline" onClick={closePreview}>Close</Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
