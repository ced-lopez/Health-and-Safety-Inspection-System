import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, Check, History, Pencil, RefreshCw, RotateCcw, Upload, X } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  fetchOcrResult,
  fetchOcrResultHistory,
  rejectOcrResult,
  reprocessOcrResult,
  requestOcrResultReupload,
  updateOcrResultFields,
  verifyOcrResult,
} from '@/services/ocrResultService'

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString()
}

function fieldLabel(field) {
  return field.replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

function confidenceBadge(score) {
  if (score == null) return <Badge variant="outline">N/A</Badge>
  const rounded = Math.round(score)
  if (score >= 90) return <Badge>{rounded}%</Badge>
  if (score >= 75) return <Badge variant="secondary">{rounded}%</Badge>
  return <Badge variant="destructive">{rounded}%</Badge>
}

export default function OcrResultDetailPage() {
  const { id } = useParams()

  const [detail, setDetail] = useState(null)
  const [detailLoading, setDetailLoading] = useState(true)
  const [edits, setEdits] = useState({})
  const [saveReason, setSaveReason] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [verifyOpen, setVerifyOpen] = useState(false)
  const [rejectOpen, setRejectOpen] = useState(false)
  const [rejectReason, setRejectReason] = useState('')
  const [reuploadOpen, setReuploadOpen] = useState(false)
  const [reprocessOpen, setReprocessOpen] = useState(false)
  const [historyOpen, setHistoryOpen] = useState(false)
  const [history, setHistory] = useState(null)
  const [historyLoading, setHistoryLoading] = useState(false)

  const { isError } = useQuery({
    queryKey: ['ocr-result', id],
    queryFn: () => fetchOcrResult(id),
  })

  useEffect(() => { if (isError) toast.error('Unable to load OCR result') }, [isError])

  async function loadDetail() {
    try {
      const res = await fetchOcrResult(id)
      setDetail(res.data ?? res)
    } catch { toast.error('Unable to load OCR result') }
    finally { setDetailLoading(false) }
  }

  useEffect(() => { loadDetail() }, [id]) // eslint-disable-line react-hooks/exhaustive-deps

  const extraction = detail?.extraction ?? {}
  const fields = extraction.fields ?? {}
  const corrections = Array.isArray(detail?.corrections) ? detail.corrections : []
  const lowConfidence = Array.isArray(extraction.low_confidence_fields)
    ? extraction.low_confidence_fields
    : Object.keys(extraction.low_confidence_fields ?? {})
  const hasChanges = Object.keys(edits).length > 0

  function applyEdit(field, value) {
    const current = fields[field] ?? { value: '', confidence: 0 }
    const original = current.value ?? ''
    const key = field

    if ((value ?? '') === (original ?? '')) {
      setEdits((prev) => {
        const next = { ...prev }
        delete next[key]
        return next
      })
      return
    }

    setEdits((prev) => ({ ...prev, [key]: { value: value ?? '', confidence: current.confidence } }))
  }

  async function saveEdits() {
    if (!hasChanges) return
    setSubmitting(true)
    try {
      await updateOcrResultFields(id, {
        fields: edits,
        reason: saveReason || undefined,
      })
      toast.success('Extracted fields updated')
      setEdits({})
      setSaveReason('')
      await loadDetail()
    } catch (err) {
      toast.error(err.response?.data?.message ?? 'Unable to update fields')
    }
    finally { setSubmitting(false) }
  }

  async function confirmVerify() {
    setSubmitting(true)
    try {
      await verifyOcrResult(id)
      setVerifyOpen(false)
      toast.success('OCR result verified')
      await loadDetail()
    } catch { toast.error('Unable to verify result') }
    finally { setSubmitting(false) }
  }

  async function confirmReject() {
    setSubmitting(true)
    try {
      await rejectOcrResult(id, { reason: rejectReason })
      setRejectOpen(false)
      setRejectReason('')
      toast.success('OCR result rejected')
      await loadDetail()
    } catch (err) { toast.error(err.response?.data?.message ?? 'Unable to reject result') }
    finally { setSubmitting(false) }
  }

  async function confirmReupload() {
    setSubmitting(true)
    try {
      await requestOcrResultReupload(id)
      setReuploadOpen(false)
      toast.success('Re-upload requested')
      await loadDetail()
    } catch { toast.error('Unable to request re-upload') }
    finally { setSubmitting(false) }
  }

  async function confirmReprocess() {
    setSubmitting(true)
    try {
      await reprocessOcrResult(id)
      setReprocessOpen(false)
      toast.success('OCR reprocessing complete')
      await loadDetail()
    } catch (err) { toast.error(err.response?.data?.message ?? 'Unable to reprocess') }
    finally { setSubmitting(false) }
  }

  async function openHistory() {
    setHistoryOpen(true)
    setHistoryLoading(true)
    try {
      const res = await fetchOcrResultHistory(id)
      setHistory(res.data ?? res)
    } catch { toast.error('Unable to load OCR history') }
    finally { setHistoryLoading(false) }
  }

  const statusBadge = (() => {
    if (extraction.verification_status === 'verified') return { label: 'Verified', variant: 'default' }
    if (extraction.verification_status === 'rejected') return { label: 'Rejected', variant: 'destructive' }
    if (detail?.status === 'needs_reupload') return { label: 'Re-upload', variant: 'outline' }
    if (extraction.ocr_status === 'needs_review') return { label: 'Needs Review', variant: 'secondary' }
    return { label: 'Pending', variant: 'outline' }
  })()

  if (detailLoading) {
    return <div className="space-y-6"><Skeleton className="h-8 w-64" /><Skeleton className="h-40 w-full" /><Skeleton className="h-64 w-full" /></div>
  }

  if (!detail) {
    return (
      <div className="space-y-6">
        <Button variant="outline" size="sm" nativeButton={false} render={<Link to="/ocr-results" />}><ArrowLeft className="size-3.5" /> Back to OCR Results</Button>
        <p className="text-sm text-muted-foreground">OCR result not found.</p>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="space-y-1">
          <Button variant="ghost" size="sm" className="-ml-2" nativeButton={false} render={<Link to="/ocr-results" />}>
            <ArrowLeft className="size-3.5" /> Back to OCR Results
          </Button>
          <h2 className="text-2xl font-semibold tracking-tight">{detail.original_name ?? detail.file_name ?? 'OCR Result'}</h2>
          <p className="text-sm text-muted-foreground">
            {detail.applicant ?? '—'}
            {detail.business_name ? ` · ${detail.business_name}` : ''}
            {detail.request_number ? ` · ${detail.request_number}` : ''}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={statusBadge.variant} className="capitalize">{statusBadge.label}</Badge>
          {detail.classification && <Badge variant="outline" className="capitalize">{detail.classification.replace(/_/g, ' ')}</Badge>}
          {confidenceBadge(extraction.confidence_score)}
          <Button variant="outline" size="sm" onClick={openHistory}>
            <History className="size-3.5" /> History
          </Button>
        </div>
      </div>

      <Tabs defaultValue="fields">
        <TabsList>
          <TabsTrigger value="fields">Extracted Fields</TabsTrigger>
          <TabsTrigger value="corrections">Corrections ({corrections.length})</TabsTrigger>
          <TabsTrigger value="text">Raw Text</TabsTrigger>
        </TabsList>

        <TabsContent value="fields" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Extracted Fields</CardTitle>
              <CardDescription>Review extracted values. Low-confidence fields are highlighted — edit and save changes.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {Object.keys(fields).length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">No fields were extracted for this document.</p>
              ) : (
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Field</TableHead>
                        <TableHead>Extracted Value</TableHead>
                        <TableHead className="w-28">Confidence</TableHead>
                        <TableHead className="w-1/3">Edit</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {Object.entries(fields).map(([field, entry]) => {
                        const value = entry?.value ?? ''
                        const confidence = typeof entry?.confidence === 'number' ? entry.confidence * 100 : 0
                        const low = lowConfidence.includes(field)
                        const isEditing = edits[field] !== undefined
                        return (
                          <TableRow key={field} className={low && !isEditing ? 'bg-destructive/5' : ''}>
                            <TableCell className="font-medium capitalize">{fieldLabel(field)}</TableCell>
                            <TableCell className="max-w-[240px]">
                              <span className={low ? 'font-medium text-destructive' : ''}>{String(value ?? '') || '—'}</span>
                            </TableCell>
                            <TableCell>{confidenceBadge(low ? confidence : confidence)}</TableCell>
                            <TableCell>
                              {isEditing ? (
                                <div className="flex items-center gap-1.5">
                                  <Input
                                    defaultValue={edits[field].value}
                                    autoFocus
                                    onChange={(e) => applyEdit(field, e.target.value)}
                                  />
                                  <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={() => setEdits((prev) => {
                                      const next = { ...prev }
                                      delete next[field]
                                      return next
                                    })}
                                    title="Cancel edit"
                                  >
                                    <X className="size-3.5" />
                                  </Button>
                                </div>
                              ) : (
                                <Button variant="outline" size="sm" onClick={() => setEdits((prev) => ({ ...prev, [field]: { value, confidence } }))} disabled={submitting}>
                                  <Pencil className="size-3.5" /> Edit
                                </Button>
                              )}
                            </TableCell>
                          </TableRow>
                        )
                      })}
                    </TableBody>
                  </Table>
                </div>
              )}

              {hasChanges && (
                <div className="space-y-3 rounded-lg border border-border p-4">
                  <div className="space-y-1.5">
                    <Label htmlFor="save-reason">Reason for correction (optional)</Label>
                    <Input id="save-reason" placeholder="e.g. OCR misread the name" value={saveReason} onChange={(e) => setSaveReason(e.target.value)} />
                  </div>
                  <Button onClick={saveEdits} disabled={submitting}>
                    <Check className="mr-1.5 size-4" /> Save Corrections
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Verification Actions</CardTitle>
              <CardDescription>Approve the extraction, request a better scan, or run OCR again</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-wrap items-center gap-2">
              <Button onClick={() => setVerifyOpen(true)} disabled={submitting || extraction.verification_status === 'verified'}>
                <Check className="mr-1.5 size-4" /> Verify
              </Button>
              <Button variant="outline" onClick={() => setRejectOpen(true)} disabled={submitting || extraction.verification_status === 'rejected'}>
                <X className="mr-1.5 size-4" /> Reject
              </Button>
              <Button variant="outline" onClick={() => setReuploadOpen(true)} disabled={submitting || detail?.status === 'needs_reupload'}>
                <Upload className="mr-1.5 size-4" /> Request Re-upload
              </Button>
              <Button variant="outline" onClick={() => setReprocessOpen(true)} disabled={submitting}>
                <RefreshCw className="mr-1.5 size-4" /> Reprocess OCR
              </Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="corrections" className="space-y-4">
          {corrections.length === 0 ? (
            <Card><CardContent className="py-10 text-center text-sm text-muted-foreground">No corrections recorded yet.</CardContent></Card>
          ) : (
            <Card>
              <CardContent className="p-4">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Field</TableHead>
                      <TableHead>Original</TableHead>
                      <TableHead>Corrected</TableHead>
                      <TableHead>Reason</TableHead>
                      <TableHead>By</TableHead>
                      <TableHead>When</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {corrections.map((correction) => (
                      <TableRow key={correction.id}>
                        <TableCell className="font-medium capitalize">{fieldLabel(correction.field_name)}</TableCell>
                        <TableCell className="max-w-[160px] truncate">{correction.original_value ?? '—'}</TableCell>
                        <TableCell className="max-w-[160px] truncate font-medium">{correction.corrected_value ?? '—'}</TableCell>
                        <TableCell className="max-w-[200px] truncate text-muted-foreground">{correction.reason ?? '—'}</TableCell>
                        <TableCell>{correction.corrected_by?.name ?? '—'}</TableCell>
                        <TableCell className="text-sm">{formatDate(correction.corrected_at)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        <TabsContent value="text" className="space-y-4">
          <Card>
            <CardContent className="p-4">
              {extraction.ocr_text ? (
                <pre className="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg bg-muted p-4 text-xs">{extraction.ocr_text}</pre>
              ) : (
                <p className="py-8 text-center text-sm text-muted-foreground">No raw OCR text available.</p>
              )}
              <Separator className="my-4" />
              <dl className="grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
                <div><dt className="text-xs text-muted-foreground">Engine</dt><dd>{extraction.ocr_engine ?? '—'}</dd></div>
                <div><dt className="text-xs text-muted-foreground">Language</dt><dd>{extraction.ocr_language ?? '—'}</dd></div>
                <div><dt className="text-xs text-muted-foreground">Passes</dt><dd>{extraction.ocr_passes ?? '—'}</dd></div>
                <div><dt className="text-xs text-muted-foreground">Processing time</dt><dd>{extraction.processing_time_ms != null ? `${extraction.processing_time_ms} ms` : '—'}</dd></div>
                <div><dt className="text-xs text-muted-foreground">Classification confidence</dt><dd>{extraction.classification_confidence != null ? `${Math.round(extraction.classification_confidence * 100)}%` : '—'}</dd></div>
                <div><dt className="text-xs text-muted-foreground">Reviewed</dt><dd>{formatDate(extraction.reviewed_at)}</dd></div>
              </dl>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={verifyOpen} onOpenChange={setVerifyOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Verify OCR Result</DialogTitle>
            <DialogDescription>Confirm the extracted data is accurate and mark this document as verified.</DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setVerifyOpen(false)}>Cancel</Button>
            <Button onClick={confirmVerify} disabled={submitting}>Confirm Verify</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Reject OCR Result</DialogTitle>
            <DialogDescription>A reason is required so the uploader knows what to fix.</DialogDescription>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="reject-reason">Reason</Label>
            <Input id="reject-reason" placeholder="e.g. Extracted information does not match the document" value={rejectReason} onChange={(e) => setRejectReason(e.target.value)} />
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setRejectOpen(false)}>Cancel</Button>
            <Button variant="destructive" onClick={confirmReject} disabled={submitting || !rejectReason.trim()}>Reject</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={reuploadOpen} onOpenChange={setReuploadOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Request Re-upload</DialogTitle>
            <DialogDescription>The applicant will be asked to upload a clearer copy of this document.</DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setReuploadOpen(false)}>Cancel</Button>
            <Button onClick={confirmReupload} disabled={submitting}>Request Re-upload</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={reprocessOpen} onOpenChange={setReprocessOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Reprocess OCR</DialogTitle>
            <DialogDescription>Re-run OCR on this document. The current extraction will be archived to history before a new attempt is made.</DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setReprocessOpen(false)}>Cancel</Button>
            <Button onClick={confirmReprocess} disabled={submitting}>
              <RotateCcw className="mr-1.5 size-4" /> Reprocess
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={historyOpen} onOpenChange={setHistoryOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>OCR History</DialogTitle>
            <DialogDescription>All extraction attempts for this document</DialogDescription>
          </DialogHeader>
          {historyLoading ? (
            <Skeleton className="h-40 w-full" />
          ) : (
            <div className="space-y-3">
              {history?.current && (
                <div className="rounded-lg border border-border p-3">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-semibold">Current extraction</p>
                    <Badge>Attempt {history.current.attempt}</Badge>
                  </div>
                  <div className="mt-2 grid grid-cols-2 gap-2 text-sm md:grid-cols-3">
                    <div><span className="text-xs text-muted-foreground">Status</span><p className="font-medium">{history.current.ocr_status}</p></div>
                    <div><span className="text-xs text-muted-foreground">Type</span><p className="capitalize">{history.current.classification?.replace(/_/g, ' ') ?? '—'}</p></div>
                    <div><span className="text-xs text-muted-foreground">Confidence</span><p className="font-medium">{confidenceBadge(history.current.confidence_score)}</p></div>
                    <div><span className="text-xs text-muted-foreground">Processed</span><p>{formatDate(history.current.processed_at)}</p></div>
                  </div>
                </div>
              )}
              {(history?.versions ?? []).map((version) => (
                <div key={version.id} className="rounded-lg border border-dashed p-3">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-semibold">Archived attempt</p>
                    <Badge variant="secondary">Attempt {version.attempt}</Badge>
                  </div>
                  <div className="mt-2 grid grid-cols-2 gap-2 text-sm md:grid-cols-3">
                    <div><span className="text-xs text-muted-foreground">Status</span><p className="font-medium">{version.ocr_status}</p></div>
                    <div><span className="text-xs text-muted-foreground">Type</span><p className="capitalize">{version.classification?.replace(/_/g, ' ') ?? '—'}</p></div>
                    <div><span className="text-xs text-muted-foreground">Confidence</span><p className="font-medium">{confidenceBadge(version.confidence_score)}</p></div>
                    <div><span className="text-xs text-muted-foreground">Engine</span><p>{version.ocr_engine ?? '—'}</p></div>
                    <div><span className="text-xs text-muted-foreground">Processed</span><p>{formatDate(version.processed_at)}</p></div>
                  </div>
                  {version.ocr_error && <p className="mt-2 text-xs text-destructive">{version.ocr_error}</p>}
                </div>
              ))}
              {!history?.current && (history?.versions ?? []).length === 0 && (
                <p className="py-6 text-center text-sm text-muted-foreground">No history recorded.</p>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}