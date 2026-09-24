import { useEffect, useMemo, useState } from 'react'
import { AlertCircle, Download, Edit, ExternalLink, Eye, FileUp, Search, Trash2 } from 'lucide-react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
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
  createViolation,
  deleteViolation,
  downloadViolationNoticePdf,
  fetchViolation,
  fetchViolationOptions,
  fetchViolations,
  updateViolation,
  uploadViolationEvidence,
} from '@/services/violationService'

const emptyForm = {
  inspection_id: '',
  inspection_result_id: '',
  assigned_to: '',
  title: '',
  description: '',
  severity: 'minor',
  status: 'open',
  correction_deadline: '',
}

const severityLabels = {
  minor: 'Minor',
  moderate: 'Moderate',
  major: 'Major',
}

const statusLabels = {
  open: 'Open',
  under_review: 'Under Review',
  resolved: 'Resolved',
}

function severityVariant(severity) {
  if (severity === 'major') {
    return 'destructive'
  }

  if (severity === 'moderate') {
    return 'secondary'
  }

  return 'outline'
}

function statusVariant(status) {
  if (status === 'resolved') {
    return 'default'
  }

  if (status === 'under_review') {
    return 'secondary'
  }

  return 'destructive'
}

function formatDate(value) {
  if (!value) {
    return 'No deadline'
  }

  return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

export default function ViolationsPage() {
  const { user } = useAuth()
  const [violations, setViolations] = useState([])
  const [inspections, setInspections] = useState([])
  const [assignees, setAssignees] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [filters, setFilters] = useState({
    search: '',
    status: 'all',
    severity: 'all',
    page: 1,
  })
  const [searchInput, setSearchInput] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})
  const [evidenceOpen, setEvidenceOpen] = useState(false)
  const [evidenceViolation, setEvidenceViolation] = useState(null)
  const [evidenceViewOpen, setEvidenceViewOpen] = useState(false)
  const [evidenceViewViolation, setEvidenceViewViolation] = useState(null)
  const [evidenceViewLoading, setEvidenceViewLoading] = useState(false)
  const [evidenceForm, setEvidenceForm] = useState({
    evidence_type: 'corrective',
    description: '',
    files: [],
  })
  const [evidenceSubmitting, setEvidenceSubmitting] = useState(false)

  const roleSlug = user?.role?.slug
  const canArchive = ['administrator', 'barangay_staff'].includes(roleSlug)

  const selectedInspection = inspections.find(
    (inspection) => String(inspection.id) === String(form.inspection_id),
  )

  const queryParams = useMemo(
    () => ({
      search: filters.search || undefined,
      status: filters.status,
      severity: filters.severity,
      page: filters.page,
      per_page: 10,
    }),
    [filters],
  )

  const optionsQuery = useQuery({
    queryKey: ['violation-options'],
    queryFn: fetchViolationOptions,
  })

  const violationsQuery = useQuery({
    queryKey: ['violations', queryParams],
    queryFn: async () => fetchViolations(queryParams),
    placeholderData: keepPreviousData,
  })

  useEffect(() => {
    if (optionsQuery.isError) {
      toast.error('Unable to load violation options')
      console.error(optionsQuery.error)
    }
  }, [optionsQuery.error, optionsQuery.isError])

  useEffect(() => {
    if (violationsQuery.isError) {
      toast.error('Unable to load violations')
      console.error(violationsQuery.error)
    }
  }, [violationsQuery.error, violationsQuery.isError])

  useEffect(() => {
    if (optionsQuery.data) {
      setInspections(optionsQuery.data.data.inspections ?? [])
      setAssignees(optionsQuery.data.data.assignees ?? [])
    }
  }, [optionsQuery.data])

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setFilters((current) => {
        if (current.search === searchInput) {
          return current
        }

        return {
          ...current,
          search: searchInput,
          page: 1,
        }
      })
    }, 150)

    return () => window.clearTimeout(timeout)
  }, [searchInput])

  useEffect(() => {
    if (violationsQuery.data) {
      const registry = violationsQuery.data.data.violations ?? []
      setViolations(registry.data ?? registry)
      setMeta(violationsQuery.data.data.meta ?? { current_page: 1, last_page: 1, total: 0 })
    }
  }, [violationsQuery.data])

  const loading = violationsQuery.isLoading && violations.length === 0

  function updateFilter(key, value) {
    setFilters((current) => ({
      ...current,
      [key]: value,
      page: key === 'page' ? value : 1,
    }))
  }

  function openCreateDialog() {
    setEditing(null)
    setForm(emptyForm)
    setErrors({})
    setDialogOpen(true)
  }

  function openEditDialog(violation) {
    setEditing(violation)
    setForm({
      inspection_id: String(violation.inspection_id ?? ''),
      inspection_result_id: String(violation.inspection_result_id ?? ''),
      assigned_to: String(violation.assigned_to ?? ''),
      title: violation.title ?? '',
      description: violation.description ?? '',
      severity: violation.severity ?? 'minor',
      status: violation.status ?? 'open',
      correction_deadline: violation.correction_deadline ?? '',
    })
    setErrors({})
    setDialogOpen(true)
  }

  function updateForm(key, value) {
    setForm((current) => ({
      ...current,
      [key]: value,
      ...(key === 'inspection_id' ? { inspection_result_id: '' } : {}),
    }))
    setErrors((current) => ({ ...current, [key]: undefined }))
  }

  function normalizePayload() {
    return {
      ...form,
      inspection_id: Number(form.inspection_id),
      inspection_result_id: form.inspection_result_id
        ? Number(form.inspection_result_id)
        : null,
      assigned_to: form.assigned_to ? Number(form.assigned_to) : null,
      correction_deadline: form.correction_deadline || null,
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setErrors({})

    try {
      if (editing) {
        await updateViolation(editing.id, normalizePayload())
        toast.success('Violation updated')
      } else {
        await createViolation(normalizePayload())
        toast.success('Violation reported')
      }

      setDialogOpen(false)
      await violationsQuery.refetch()
    } catch (error) {
      const validationErrors = error.response?.data?.errors

      if (validationErrors) {
        setErrors(validationErrors)
        toast.error('Please review the highlighted fields')
      } else {
        toast.error('Unable to save violation')
      }

      console.error(error)
    } finally {
      setSubmitting(false)
    }
  }

  async function handleArchive(violation) {
    const confirmed = window.confirm(`Archive violation "${violation.title}"?`)

    if (!confirmed) {
      return
    }

    try {
      await deleteViolation(violation.id)
      toast.success('Violation archived')
      await violationsQuery.refetch()
    } catch (error) {
      toast.error('Unable to archive violation')
      console.error(error)
    }
  }

  function openEvidenceDialog(violation) {
    setEvidenceViolation(violation)
    setEvidenceForm({
      evidence_type: 'corrective',
      description: '',
      files: [],
    })
    setEvidenceOpen(true)
  }

  async function openEvidenceViewDialog(violation) {
    setEvidenceViewViolation(violation)
    setEvidenceViewOpen(true)
    setEvidenceViewLoading(true)

    try {
      const response = await fetchViolation(violation.id)
      setEvidenceViewViolation(response.data)
    } catch (error) {
      toast.error('Unable to load evidence files')
      console.error(error)
    } finally {
      setEvidenceViewLoading(false)
    }
  }

  async function handleEvidenceSubmit(event) {
    event.preventDefault()

    if (!evidenceViolation) {
      return
    }

    setEvidenceSubmitting(true)

    try {
      const payload = new FormData()
      payload.append('evidence_type', evidenceForm.evidence_type)
      payload.append('description', evidenceForm.description)
      evidenceForm.files.forEach((file) => payload.append('files[]', file))

      await uploadViolationEvidence(evidenceViolation.id, payload)
      toast.success('Evidence uploaded')
      setEvidenceOpen(false)
      await violationsQuery.refetch()
    } catch (error) {
      toast.error('Unable to upload evidence')
      console.error(error)
    } finally {
      setEvidenceSubmitting(false)
    }
  }

  async function handleDownloadNotice(violation) {
    try {
      const blob = await downloadViolationNoticePdf(violation.id)
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `violation-notice-${violation.id}.pdf`
      document.body.appendChild(a)
      a.click()
      a.remove()
      URL.revokeObjectURL(url)
    } catch (error) {
      toast.error('Unable to download violation notice PDF')
      console.error(error)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-2xl font-semibold tracking-tight">Violations</h2>
            {violationsQuery.isFetching && !loading && (
              <span className="text-xs text-muted-foreground">Refreshing...</span>
            )}
          </div>
          <p className="text-sm text-muted-foreground">
            Track safety violations, deadlines, evidence, and resolution status
          </p>
        </div>
        <Button variant="destructive" onClick={openCreateDialog}>
          <AlertCircle className="size-4" />
          Report Violation
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Violation Tracker</CardTitle>
          <CardDescription>
            Monitor violations from reporting through corrective review
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 lg:grid-cols-[1fr_180px_180px]">
            <div className="relative">
              <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
              <Input
                className="pl-8"
                placeholder="Search violation, establishment, or registration no."
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
              />
            </div>
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
            <select
              className="h-8 rounded-lg border border-input bg-background px-2.5 text-sm"
              value={filters.severity}
              onChange={(event) => updateFilter('severity', event.target.value)}
            >
              <option value="all">All severities</option>
              {Object.entries(severityLabels).map(([value, label]) => (
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
          ) : violations.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">
              No violations found.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Violation</TableHead>
                    <TableHead>Establishment</TableHead>
                    <TableHead>Deadline</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {violations.map((violation) => (
                    <TableRow key={violation.id}>
                      <TableCell>
                        <div className="font-medium">{violation.title}</div>
                        <div className="mt-1 flex flex-wrap gap-2">
                          <Badge variant={severityVariant(violation.severity)}>
                            {severityLabels[violation.severity] ?? violation.severity}
                          </Badge>
                          <span className="text-xs text-muted-foreground">
                            {violation.evidence_count ?? violation.evidence?.length ?? 0}{' '}
                            evidence files
                          </span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div>{violation.establishment?.name ?? 'Unknown'}</div>
                        <div className="text-xs text-muted-foreground">
                          {violation.establishment?.registration_number ?? 'No registration'}
                        </div>
                      </TableCell>
                      <TableCell>{formatDate(violation.correction_deadline)}</TableCell>
                      <TableCell>
                        <Badge variant={statusVariant(violation.status)}>
                          {statusLabels[violation.status] ?? violation.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex justify-end gap-2">
                          <Button
                            variant="outline"
                            size="icon-sm"
                            aria-label="View evidence"
                            onClick={() => openEvidenceViewDialog(violation)}
                          >
                            <Eye className="size-4" />
                          </Button>
                          <Button
                            variant="outline"
                            size="icon-sm"
                            aria-label="Upload evidence"
                            onClick={() => openEvidenceDialog(violation)}
                          >
                            <FileUp className="size-4" />
                          </Button>
                          <Button
                            variant="outline"
                            size="icon-sm"
                            aria-label="Download violation notice PDF"
                            onClick={() => handleDownloadNotice(violation)}
                          >
                            <Download className="size-4" />
                          </Button>
                          <Button
                            variant="outline"
                            size="icon-sm"
                            aria-label="Edit violation"
                            onClick={() => openEditDialog(violation)}
                          >
                            <Edit className="size-4" />
                          </Button>
                          {canArchive && (
                            <Button
                              variant="destructive"
                              size="icon-sm"
                              aria-label="Archive violation"
                              onClick={() => handleArchive(violation)}
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

          <div className="flex flex-col gap-3 border-t pt-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <span>{meta.total} violations</span>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={loading || meta.current_page <= 1}
                onClick={() => updateFilter('page', meta.current_page - 1)}
              >
                Previous
              </Button>
              <span>
                Page {meta.current_page} of {meta.last_page}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={loading || meta.current_page >= meta.last_page}
                onClick={() => updateFilter('page', meta.current_page + 1)}
              >
                Next
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Violation' : 'Report Violation'}</DialogTitle>
            <DialogDescription>
              Link violations to an inspection and track correction progress.
            </DialogDescription>
          </DialogHeader>

          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid gap-4 sm:grid-cols-2">
              <SelectField
                label="Inspection"
                value={form.inspection_id}
                error={errors.inspection_id}
                onChange={(value) => updateForm('inspection_id', value)}
              >
                <option value="">Select inspection</option>
                {inspections.map((inspection) => (
                  <option key={inspection.id} value={inspection.id}>
                    {inspection.establishment?.name} - {inspection.inspection_date}
                  </option>
                ))}
              </SelectField>
              <SelectField
                label="Checklist finding"
                value={form.inspection_result_id}
                error={errors.inspection_result_id}
                required={false}
                onChange={(value) => updateForm('inspection_result_id', value)}
              >
                <option value="">No linked finding</option>
                {(selectedInspection?.results ?? []).map((result) => (
                  <option key={result.id} value={result.id}>
                    {result.item_title} ({result.compliance_status})
                  </option>
                ))}
              </SelectField>
              <Field
                label="Title"
                value={form.title}
                error={errors.title}
                onChange={(value) => updateForm('title', value)}
              />
              <SelectField
                label="Assigned to"
                value={form.assigned_to}
                error={errors.assigned_to}
                required={false}
                onChange={(value) => updateForm('assigned_to', value)}
              >
                <option value="">Unassigned</option>
                {assignees.map((assignee) => (
                  <option key={assignee.id} value={assignee.id}>
                    {assignee.name}
                  </option>
                ))}
              </SelectField>
              <SelectField
                label="Severity"
                value={form.severity}
                error={errors.severity}
                onChange={(value) => updateForm('severity', value)}
              >
                {Object.entries(severityLabels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </SelectField>
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
              <Field
                label="Correction deadline"
                type="date"
                value={form.correction_deadline}
                error={errors.correction_deadline}
                required={false}
                onChange={(value) => updateForm('correction_deadline', value)}
              />
            </div>

            <div className="space-y-2">
              <Label>Description</Label>
              <textarea
                className="min-h-24 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                value={form.description}
                onChange={(event) => updateForm('description', event.target.value)}
                required
              />
              {errors.description && (
                <p className="text-xs text-destructive">{errors.description[0]}</p>
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
                {submitting ? 'Saving...' : 'Save Violation'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={evidenceOpen} onOpenChange={setEvidenceOpen}>
        <DialogContent className="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle>Upload Evidence</DialogTitle>
            <DialogDescription>{evidenceViolation?.title}</DialogDescription>
          </DialogHeader>
          <form className="space-y-4" onSubmit={handleEvidenceSubmit}>
            <SelectField
              label="Evidence type"
              value={evidenceForm.evidence_type}
              onChange={(value) =>
                setEvidenceForm((current) => ({
                  ...current,
                  evidence_type: value,
                }))
              }
            >
              <option value="initial">Initial</option>
              <option value="corrective">Corrective</option>
            </SelectField>
            <div className="space-y-2">
              <Label>Description</Label>
              <Input
                value={evidenceForm.description}
                onChange={(event) =>
                  setEvidenceForm((current) => ({
                    ...current,
                    description: event.target.value,
                  }))
                }
              />
            </div>
            <div className="space-y-2">
              <Label>Files</Label>
              <Input
                type="file"
                multiple
                required
                onChange={(event) =>
                  setEvidenceForm((current) => ({
                    ...current,
                    files: Array.from(event.target.files ?? []),
                  }))
                }
              />
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEvidenceOpen(false)}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={evidenceSubmitting}>
                {evidenceSubmitting ? 'Uploading...' : 'Upload Evidence'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={evidenceViewOpen} onOpenChange={setEvidenceViewOpen}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>Evidence Files</DialogTitle>
            <DialogDescription>{evidenceViewViolation?.title}</DialogDescription>
          </DialogHeader>

          {evidenceViewLoading ? (
            <div className="space-y-3">
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-16 w-full" />
            </div>
          ) : (evidenceViewViolation?.evidence ?? []).length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">
              No evidence files uploaded for this violation.
            </p>
          ) : (
            <div className="space-y-3">
              {(evidenceViewViolation?.evidence ?? []).map((evidence) => (
                <div
                  key={evidence.id}
                  className="flex flex-col gap-3 rounded-lg border border-border p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium">{evidence.file_name}</p>
                    <div className="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                      <span>{evidence.evidence_type}</span>
                      <span>{evidence.mime_type ?? 'Unknown file type'}</span>
                      <span>
                        {evidence.created_at
                          ? new Date(evidence.created_at).toLocaleString()
                          : 'No upload date'}
                      </span>
                    </div>
                    {evidence.description && (
                      <p className="mt-2 text-sm text-muted-foreground">
                        {evidence.description}
                      </p>
                    )}
                  </div>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                      window.open(evidence.file_url, '_blank', 'noreferrer')
                    }
                  >
                    <ExternalLink className="size-4" />
                    Open
                  </Button>
                </div>
              ))}
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
