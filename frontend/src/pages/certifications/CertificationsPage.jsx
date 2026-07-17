import { useEffect, useMemo, useState } from 'react'
import { Edit, FileCheck, Printer, Search, Trash2 } from 'lucide-react'
import { toast } from 'sonner'

import { useAuth } from '@/context/AuthContext'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  createCertificationDocument,
  deleteCertificationDocument,
  fetchCertificationDocuments,
  fetchCertificationOptions,
  updateCertificationDocument,
} from '@/services/certificationService'

const emptyForm = {
  document_kind: 'certification',
  establishment_id: '',
  inspection_id: '',
  document_type: '',
  purpose: '',
  issue_date: new Date().toISOString().slice(0, 10),
  expiration_date: '',
  status: 'active',
  notes: '',
}

const kindLabels = {
  certification: 'Certification',
  clearance: 'Clearance',
}

const statusLabels = {
  pending: 'Pending',
  active: 'Active',
  expired: 'Expired',
  revoked: 'Revoked',
}

function statusVariant(status) {
  if (status === 'active') {
    return 'default'
  }

  if (status === 'revoked' || status === 'expired') {
    return 'destructive'
  }

  return 'secondary'
}

function formatDate(value) {
  if (!value) {
    return 'No expiration'
  }

  return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

export default function CertificationsPage() {
  const { user } = useAuth()
  const [documents, setDocuments] = useState([])
  const [establishments, setEstablishments] = useState([])
  const [inspections, setInspections] = useState([])
  const [filters, setFilters] = useState({
    search: '',
    document_kind: 'all',
    status: 'all',
  })
  const [searchInput, setSearchInput] = useState('')
  const [loading, setLoading] = useState(true)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [preview, setPreview] = useState(null)

  const canArchive = user?.role?.slug === 'administrator'

  const queryParams = useMemo(
    () => ({
      search: filters.search || undefined,
      document_kind: filters.document_kind,
      status: filters.status,
    }),
    [filters],
  )

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setFilters((current) => ({ ...current, search: searchInput }))
    }, 300)

    return () => window.clearTimeout(timeout)
  }, [searchInput])

  useEffect(() => {
    loadOptions()
  }, [])

  useEffect(() => {
    loadDocuments()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [queryParams])

  async function loadOptions() {
    try {
      const response = await fetchCertificationOptions()
      setEstablishments(response.data.establishments ?? [])
      setInspections(response.data.inspections ?? [])
    } catch (error) {
      toast.error('Unable to load issuance options')
      console.error(error)
    }
  }

  async function loadDocuments() {
    setLoading(true)

    try {
      const response = await fetchCertificationDocuments(queryParams)
      setDocuments(response.data.documents ?? [])
    } catch (error) {
      toast.error('Unable to load certifications and clearances')
      console.error(error)
    } finally {
      setLoading(false)
    }
  }

  function updateFilter(key, value) {
    setFilters((current) => ({ ...current, [key]: value }))
  }

  function updateForm(key, value) {
    setForm((current) => ({
      ...current,
      [key]: value,
      ...(key === 'inspection_id' && value
        ? {
            establishment_id:
              inspections.find((inspection) => String(inspection.id) === value)
                ?.establishment_id ?? current.establishment_id,
          }
        : {}),
    }))
    setErrors((current) => ({ ...current, [key]: undefined }))
  }

  function openCreateDialog(kind = 'certification') {
    setEditing(null)
    setForm({ ...emptyForm, document_kind: kind })
    setErrors({})
    setDialogOpen(true)
  }

  function openEditDialog(document) {
    setEditing(document)
    setForm({
      document_kind: document.document_kind,
      establishment_id: String(document.establishment_id ?? ''),
      inspection_id: String(document.inspection_id ?? ''),
      document_type: document.document_type ?? '',
      purpose: document.purpose ?? '',
      issue_date: document.issue_date ?? '',
      expiration_date: document.expiration_date ?? '',
      status: document.status ?? 'active',
      notes: document.notes ?? '',
    })
    setErrors({})
    setDialogOpen(true)
  }

  function normalizePayload() {
    return {
      ...form,
      establishment_id: Number(form.establishment_id),
      inspection_id: form.inspection_id ? Number(form.inspection_id) : null,
      expiration_date: form.expiration_date || null,
      purpose: form.document_kind === 'clearance' ? form.purpose : null,
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setErrors({})

    try {
      if (editing) {
        await updateCertificationDocument(editing.document_kind, editing.id, normalizePayload())
        toast.success('Document updated')
      } else {
        await createCertificationDocument(normalizePayload())
        toast.success('Document issued')
      }

      setDialogOpen(false)
      await loadDocuments()
    } catch (error) {
      const validationErrors = error.response?.data?.errors

      if (validationErrors) {
        setErrors(validationErrors)
        toast.error('Please review the highlighted fields')
      } else {
        toast.error('Unable to save document')
      }

      console.error(error)
    } finally {
      setSubmitting(false)
    }
  }

  async function handleArchive(document) {
    const confirmed = window.confirm(`Archive ${document.number}?`)

    if (!confirmed) {
      return
    }

    try {
      await deleteCertificationDocument(document.document_kind, document.id)
      toast.success('Document archived')
      await loadDocuments()
    } catch (error) {
      toast.error('Unable to archive document')
      console.error(error)
    }
  }

  function printPreview() {
    window.print()
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold tracking-tight">
            Certifications & Clearances
          </h2>
          <p className="text-sm text-muted-foreground">
            Review inspection results, issue documents, and verify QR codes
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => openCreateDialog('clearance')}>
            <FileCheck className="size-4" />
            Issue Clearance
          </Button>
          <Button onClick={() => openCreateDialog('certification')}>
            <FileCheck className="size-4" />
            Issue Certificate
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Document Registry</CardTitle>
          <CardDescription>
            Manage active, pending, expired, and revoked documents
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 lg:grid-cols-[1fr_180px_180px]">
            <div className="relative">
              <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
              <Input
                className="pl-8"
                placeholder="Search document no., type, or establishment"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
              />
            </div>
            <select
              className="h-8 rounded-lg border border-input bg-background px-2.5 text-sm"
              value={filters.document_kind}
              onChange={(event) => updateFilter('document_kind', event.target.value)}
            >
              <option value="all">All documents</option>
              <option value="certification">Certifications</option>
              <option value="clearance">Clearances</option>
            </select>
            <select
              className="h-8 rounded-lg border border-input bg-background px-2.5 text-sm"
              value={filters.status}
              onChange={(event) => updateFilter('status', event.target.value)}
            >
              <option value="all">All statuses</option>
              {Object.entries(statusLabels).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </div>

          {loading ? (
            <div className="space-y-3">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </div>
          ) : documents.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">
              No certification or clearance documents found.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Document</TableHead>
                    <TableHead>Establishment</TableHead>
                    <TableHead>Validity</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {documents.map((document) => (
                    <TableRow key={`${document.document_kind}-${document.id}`}>
                      <TableCell>
                        <div className="font-medium">{document.number}</div>
                        <div className="text-xs text-muted-foreground">
                          {kindLabels[document.document_kind]} - {document.document_type}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div>{document.establishment?.name ?? 'Unknown'}</div>
                        <div className="text-xs text-muted-foreground">
                          {document.establishment?.registration_number ?? 'No registration'}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div>{formatDate(document.issue_date)}</div>
                        <div className="text-xs text-muted-foreground">
                          Expires {formatDate(document.expiration_date)}
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant={statusVariant(document.status)}>
                          {statusLabels[document.status] ?? document.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex justify-end gap-2">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setPreview(document)}
                          >
                            View
                          </Button>
                          <Button
                            variant="outline"
                            size="icon-sm"
                            aria-label="Edit document"
                            onClick={() => openEditDialog(document)}
                          >
                            <Edit className="size-4" />
                          </Button>
                          {canArchive && (
                            <Button
                              variant="destructive"
                              size="icon-sm"
                              aria-label="Archive document"
                              onClick={() => handleArchive(document)}
                            >
                              <Trash2 className="size-4" />
                            </Button>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
          <DialogHeader>
            <DialogTitle>
              {editing ? 'Edit Document' : `Issue ${kindLabels[form.document_kind]}`}
            </DialogTitle>
            <DialogDescription>
              Final approval remains with authorized barangay personnel.
            </DialogDescription>
          </DialogHeader>

          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid gap-4 sm:grid-cols-2">
              {!editing && (
                <SelectField
                  label="Document kind"
                  value={form.document_kind}
                  error={errors.document_kind}
                  onChange={(value) => updateForm('document_kind', value)}
                >
                  <option value="certification">Certification</option>
                  <option value="clearance">Clearance</option>
                </SelectField>
              )}
              <SelectField
                label="Establishment"
                value={form.establishment_id}
                error={errors.establishment_id}
                onChange={(value) => updateForm('establishment_id', value)}
              >
                <option value="">Select establishment</option>
                {establishments.map((establishment) => (
                  <option key={establishment.id} value={establishment.id}>
                    {establishment.name}
                  </option>
                ))}
              </SelectField>
              <SelectField
                label="Inspection"
                value={form.inspection_id}
                error={errors.inspection_id}
                required={false}
                onChange={(value) => updateForm('inspection_id', value)}
              >
                <option value="">No linked inspection</option>
                {inspections.map((inspection) => (
                  <option key={inspection.id} value={inspection.id}>
                    {inspection.establishment?.name} - {inspection.inspection_date}
                  </option>
                ))}
              </SelectField>
              <Field
                label="Document type"
                value={form.document_type}
                error={errors.document_type}
                onChange={(value) => updateForm('document_type', value)}
              />
              {form.document_kind === 'clearance' && (
                <Field
                  label="Purpose"
                  value={form.purpose}
                  error={errors.purpose}
                  required={false}
                  onChange={(value) => updateForm('purpose', value)}
                />
              )}
              <Field
                label="Issue date"
                type="date"
                value={form.issue_date}
                error={errors.issue_date}
                onChange={(value) => updateForm('issue_date', value)}
              />
              <Field
                label="Expiration date"
                type="date"
                value={form.expiration_date}
                error={errors.expiration_date}
                required={false}
                onChange={(value) => updateForm('expiration_date', value)}
              />
              <SelectField
                label="Status"
                value={form.status}
                error={errors.status}
                onChange={(value) => updateForm('status', value)}
              >
                {Object.entries(statusLabels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </SelectField>
            </div>

            <div className="space-y-2">
              <Label>Notes</Label>
              <textarea
                className="min-h-24 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                value={form.notes}
                onChange={(event) => updateForm('notes', event.target.value)}
              />
              {errors.notes && (
                <p className="text-xs text-destructive">{errors.notes[0]}</p>
              )}
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setDialogOpen(false)}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting ? 'Saving...' : 'Save Document'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={Boolean(preview)} onOpenChange={(open) => !open && setPreview(null)}>
        <DialogContent className="sm:max-w-3xl">
          {preview && (
            <div className="space-y-4">
              <DialogHeader>
                <DialogTitle>{preview.document_type}</DialogTitle>
                <DialogDescription>{preview.number}</DialogDescription>
              </DialogHeader>

              <div className="rounded-lg border border-border p-5">
                <div className="text-center">
                  <p className="text-sm text-muted-foreground">
                    Barangay 178, North Caloocan City
                  </p>
                  <h3 className="mt-2 text-2xl font-semibold">
                    {kindLabels[preview.document_kind]}
                  </h3>
                  <p className="mt-1 text-sm">{preview.document_type}</p>
                </div>

                <div className="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                  <Detail label="Document No." value={preview.number} />
                  <Detail label="Status" value={statusLabels[preview.status]} />
                  <Detail label="Establishment" value={preview.establishment?.name} />
                  <Detail label="Owner" value={preview.establishment?.owner_name} />
                  <Detail label="Issue Date" value={formatDate(preview.issue_date)} />
                  <Detail label="Expiration" value={formatDate(preview.expiration_date)} />
                  {preview.purpose && <Detail label="Purpose" value={preview.purpose} />}
                  <Detail label="Issued By" value={preview.issuer?.name} />
                </div>

                <div className="mt-6 rounded-lg bg-muted p-3 text-center text-sm">
                  <p className="font-medium">QR Verification Code</p>
                  <p className="mt-1 font-mono">{preview.qr_code?.code ?? 'Pending'}</p>
                  {preview.qr_code?.code && (
                    <p className="mt-1 text-xs text-muted-foreground">
                      Verify at /api/v1/verify/{preview.qr_code.code}
                    </p>
                  )}
                </div>
              </div>

              <DialogFooter>
                <Button variant="outline" onClick={printPreview}>
                  <Printer className="size-4" />
                  Print / Save PDF
                </Button>
              </DialogFooter>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}

function Field({
  label,
  value,
  onChange,
  error,
  type = 'text',
  required = true,
}) {
  const id = label.toLowerCase().replaceAll(' ', '-')

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <Input
        id={id}
        type={type}
        value={value}
        required={required}
        onChange={(event) => onChange(event.target.value)}
      />
      {error && <p className="text-xs text-destructive">{error[0]}</p>}
    </div>
  )
}

function SelectField({
  label,
  value,
  onChange,
  error,
  children,
  required = true,
}) {
  const id = label.toLowerCase().replaceAll(' ', '-')

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <select
        id={id}
        className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
        value={value}
        required={required}
        onChange={(event) => onChange(event.target.value)}
      >
        {children}
      </select>
      {error && <p className="text-xs text-destructive">{error[0]}</p>}
    </div>
  )
}

function Detail({ label, value }) {
  return (
    <div>
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="font-medium">{value ?? 'N/A'}</p>
    </div>
  )
}
