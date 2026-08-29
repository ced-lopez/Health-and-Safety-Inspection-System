import { useEffect, useState } from 'react'
import { Download, Eye, FileSearch, FileText, Maximize2, Minimize2, Search, ShieldCheck, Upload } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { fetchInspectionRequests, fetchRequestDocuments, uploadRequestDocument } from '@/services/inspectionRequestService'
import { fetchDocument, fetchDocumentFile, verifyDocument, processDocumentOcr } from '@/services/documentService'

function formatDate(value) {
  if (!value) return ''
  return new Date(value).toLocaleString()
}

export default function DocumentsPage() {
  const [requests, setRequests] = useState([])
  const [searchInput, setSearchInput] = useState('')
  const [selectedRequest, setSelectedRequest] = useState(null)
  const [documents, setDocuments] = useState([])
  const [docOpen, setDocOpen] = useState(false)
  const [docLoading, setDocLoading] = useState(false)
  const [detailOpen, setDetailOpen] = useState(false)
  const [selectedDoc, setSelectedDoc] = useState(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [verifying, setVerifying] = useState(false)
  const [processingOcr, setProcessingOcr] = useState(false)
  const [previewOpen, setPreviewOpen] = useState(false)
  const [previewUrl, setPreviewUrl] = useState(null)
  const [previewMime, setPreviewMime] = useState('')
  const [previewName, setPreviewName] = useState('')
  const [previewLoading, setPreviewLoading] = useState(false)
  const [previewMaximized, setPreviewMaximized] = useState(false)

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['docs-inspection-requests'],
    queryFn: () => fetchInspectionRequests({ per_page: 50 }),
  })

  useEffect(() => { if (isError) toast.error('Unable to load documents') }, [isError])
  useEffect(() => {
    if (!data) return
    const raw = data.data?.inspection_requests ?? data.data
    setRequests(Array.isArray(raw) ? raw : Array.isArray(raw?.data) ? raw.data : [])
  }, [data])

  const filtered = requests.filter((r) =>
    (r.business_name ?? '').toLowerCase().includes(searchInput.toLowerCase()) ||
    (r.name ?? '').toLowerCase().includes(searchInput.toLowerCase())
  )

  async function openDocuments(request) {
    setSelectedRequest(request)
    setDocOpen(true)
    setDocLoading(true)
    try {
      const res = await fetchRequestDocuments(request.id)
      setDocuments(res.data?.documents ?? res ?? [])
    } catch { toast.error('Unable to load documents') }
    finally { setDocLoading(false) }
  }

  async function openDetail(document) {
    setDetailOpen(true)
    setDetailLoading(true)
    try {
      const res = await fetchDocument(document.id)
      setSelectedDoc(res.data ?? res)
    } catch { toast.error('Unable to load document details') }
    finally { setDetailLoading(false) }
  }

  async function openPreview(document) {
    setPreviewOpen(true)
    setPreviewLoading(true)
    setPreviewUrl(null)
    setPreviewMaximized(false)
    setPreviewName(document.original_name ?? document.file_name ?? 'document')
    try {
      const blob = await fetchDocumentFile(document.id)
      const mime = blob.type || document.mime_type || 'application/octet-stream'
      setPreviewMime(mime)
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

  async function handleVerify() {
    if (!selectedDoc) return
    setVerifying(true)
    try {
      await verifyDocument(selectedDoc.id, { status: 'verified' })
      toast.success('Document verified')
      setSelectedDoc((prev) => ({ ...prev, status: 'verified' }))
    } catch { toast.error('Verification failed') }
    finally { setVerifying(false) }
  }

  async function handleOcr() {
    if (!selectedDoc) return
    setProcessingOcr(true)
    try {
      const res = await processDocumentOcr(selectedDoc.id)
      toast.success('OCR processing complete')
      setSelectedDoc((prev) => ({ ...prev, ...(res.data ?? res) }))
    } catch { toast.error('OCR processing failed') }
    finally { setProcessingOcr(false) }
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight">Documents</h2>
        <p className="text-sm text-muted-foreground">Review, verify, and process OCR for uploaded documents</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Document Registry</CardTitle>
          <CardDescription>Documents uploaded by applicants grouped by request</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="relative max-w-sm">
            <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
            <Input className="pl-8" placeholder="Search by business name..." value={searchInput} onChange={(e) => setSearchInput(e.target.value)} />
          </div>

          {isLoading ? (
            <div className="space-y-3"><Skeleton className="h-10 w-full" /><Skeleton className="h-10 w-full" /></div>
          ) : filtered.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">No requests with documents found.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Business</TableHead>
                  <TableHead>Category</TableHead>
                  <TableHead>Documents</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.map((req) => (
                  <TableRow key={req.id}>
                    <TableCell className="font-medium">{req.business_name ?? 'N/A'}</TableCell>
                    <TableCell className="capitalize">{req.inspection_category?.name ?? 'N/A'}</TableCell>
                    <TableCell>{req.documents_count ?? req.documents?.length ?? 0}</TableCell>
                    <TableCell className="text-right">
                      <Button variant="outline" size="sm" onClick={() => openDocuments(req)}>
                        <Eye className="size-4" />
                        View
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={docOpen} onOpenChange={setDocOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>Documents</DialogTitle>
            <DialogDescription>{selectedRequest?.business_name ?? 'Request'}</DialogDescription>
          </DialogHeader>
          {docLoading ? (
            <Skeleton className="h-32 w-full" />
          ) : documents.length === 0 ? (
            <p className="py-6 text-sm text-muted-foreground text-center">No documents uploaded.</p>
          ) : (
            <div className="space-y-2">
              {Array.isArray(documents) && documents.map((doc) => (
                <div key={doc.id} className="flex items-center justify-between rounded-lg border border-border p-3">
                  <div className="flex items-center gap-2 min-w-0">
                    <FileText className="size-4 shrink-0 text-muted-foreground" />
                    <div className="min-w-0">
                      <p className="text-sm font-medium truncate">{doc.original_name ?? doc.file_name ?? doc.filename ?? 'Document'}</p>
                      <p className="text-xs text-muted-foreground">{formatDate(doc.created_at)}</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    <Badge variant={doc.status === 'verified' ? 'default' : doc.status === 'processed' ? 'secondary' : 'outline'}>{doc.status ?? 'pending'}</Badge>
                    <Button variant="outline" size="icon-sm" onClick={() => openPreview(doc)} title="View file">
                      <FileSearch className="size-4" />
                    </Button>
                    <Button variant="outline" size="icon-sm" onClick={() => openDetail(doc)}>
                      <Eye className="size-4" />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={detailOpen} onOpenChange={setDetailOpen}>
        <DialogContent className="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle>Document Details</DialogTitle>
            <DialogDescription>{selectedDoc?.original_name ?? selectedDoc?.file_name ?? selectedDoc?.filename ?? ''}</DialogDescription>
          </DialogHeader>
          {detailLoading ? (
            <Skeleton className="h-32 w-full" />
          ) : selectedDoc ? (
            <div className="space-y-4">
              <div className="grid gap-3 text-sm sm:grid-cols-2">
                <div><p className="text-xs text-muted-foreground">File Name</p><p className="font-medium">{selectedDoc.original_name ?? selectedDoc.file_name ?? selectedDoc.filename ?? 'N/A'}</p></div>
                <div><p className="text-xs text-muted-foreground">Status</p><p className="font-medium"><Badge variant={selectedDoc.status === 'verified' ? 'default' : selectedDoc.status === 'processed' ? 'secondary' : 'outline'}>{selectedDoc.status ?? 'pending'}</Badge></p></div>
                <div><p className="text-xs text-muted-foreground">Type</p><p className="font-medium capitalize">{selectedDoc.document_type ?? selectedDoc.mime_type ?? 'N/A'}</p></div>
                <div><p className="text-xs text-muted-foreground">Uploaded</p><p className="font-medium">{formatDate(selectedDoc.created_at)}</p></div>
              </div>
              {selectedDoc.extraction ? (
                <div className="space-y-3 rounded-lg border border-border p-3">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm font-medium">
                      OCR Result · {extractionLabel(selectedDoc.extraction)}
                    </p>
                    <Badge variant="secondary">{selectedDoc.extraction.confidence_score ?? 0}% confidence</Badge>
                    {selectedDoc.extraction.is_expired && <Badge variant="destructive">Expired</Badge>}
                  </div>
                  <div className="grid gap-2 text-sm sm:grid-cols-2">
                    <FieldRow label="Business Name" value={selectedDoc.extraction.business_name} />
                    <FieldRow label="Owner" value={selectedDoc.extraction.owner_name} />
                    <FieldRow label="Permit / License No." value={selectedDoc.extraction.permit_number} />
                    <FieldRow label="Issuing Authority" value={selectedDoc.extraction.issuing_authority} />
                    <FieldRow label="Date Issued" value={selectedDoc.extraction.date_issued} />
                    <FieldRow label="Expiration Date" value={selectedDoc.extraction.expiration_date} />
                  </div>
                  {Array.isArray(selectedDoc.extraction.missing_requirements) && selectedDoc.extraction.missing_requirements.length > 0 && (
                    <div className="space-y-1">
                      <p className="text-xs text-muted-foreground">Missing Requirements</p>
                      <div className="flex flex-wrap gap-2">
                        {selectedDoc.extraction.missing_requirements.map((req, idx) => (
                          <Badge key={idx} variant="destructive">{req.document_name ?? req.document_type}</Badge>
                        ))}
                      </div>
                    </div>
                  )}
                  {selectedDoc.extraction.extracted_data?.warning && (
                    <p className="text-xs text-yellow-600">{selectedDoc.extraction.extracted_data.warning}</p>
                  )}
                  {selectedDoc.extraction.extracted_data?.ocr_text && (
                    <div className="space-y-1">
                      <p className="text-xs text-muted-foreground">Extracted Text</p>
                      <div className="rounded-lg bg-muted p-3 text-xs max-h-32 overflow-y-auto whitespace-pre-wrap">{selectedDoc.extraction.extracted_data.ocr_text}</div>
                    </div>
                  )}
                </div>
              ) : (
                <div className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                  Document has not been OCR-processed yet.
                </div>
              )}
              <DialogFooter className="gap-2">
                <Button variant="outline" onClick={() => openPreview(selectedDoc)} disabled={previewLoading}>
                  <FileSearch className="size-4" />
                  View File
                </Button>
                {selectedDoc.status !== 'verified' && (
                  <Button variant="outline" onClick={handleVerify} disabled={verifying}>
                    <ShieldCheck className="size-4" />
                    {verifying ? 'Verifying...' : 'Verify'}
                  </Button>
                )}
                <Button variant="outline" onClick={handleOcr} disabled={processingOcr}>
                  <Upload className="size-4" />
                  {processingOcr ? 'Processing...' : 'Process OCR'}
                </Button>
              </DialogFooter>
            </div>
          ) : <p className="text-sm text-muted-foreground text-center py-4">No details.</p>}
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
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => { if (previewUrl) { const a = document.createElement('a'); a.href = previewUrl; a.download = previewName; a.click(); } }}>
              <Download className="size-4" />
              Download
            </Button>
            <Button variant="outline" onClick={closePreview}>Close</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function FieldRow({ label, value }) {
  return (
    <div className="min-w-0">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="truncate font-medium">{value || '—'}</p>
    </div>
  )
}

function extractionLabel(extraction) {
  if (extraction.extracted_data?.classification_label) return extraction.extracted_data.classification_label
  return (extraction.classification ?? 'document').replace(/_/g, ' ')
}
