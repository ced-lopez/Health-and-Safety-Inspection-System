import { useEffect, useMemo, useState } from 'react'
import {
  ArrowLeft,
  Award,
  Building2,
  CalendarPlus,
  CalendarDays,
  CheckCircle2,
  ClipboardCheck,
  Edit,
  FileText,
  Gavel,
  Link2,
  MapPin,
  RefreshCw,
  ScanText,
  ShieldAlert,
  Store,
  User as UserIcon,
} from 'lucide-react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { ESTABLISHMENT_CATEGORIES } from '@/utils/constants'
import { cn } from '@/lib/utils'
import {
  fetchEstablishmentProfile,
  updateEstablishment,
} from '@/services/establishmentService'

const statusLabels = {
  active: 'Active',
  inactive: 'Inactive',
  pending: 'Pending',
}

const documentStatusLabels = {
  pending: 'Pending',
  processed: 'Processed',
  verified: 'Verified',
  rejected: 'Rejected',
}

const certificateStatusLabels = {
  pending: 'Pending',
  active: 'Active',
  expired: 'Expired',
  revoked: 'Revoked',
}

const violationSeverityLabels = {
  minor: 'Minor',
  moderate: 'Moderate',
  major: 'Major',
}

const violationStatusLabels = {
  open: 'Open',
  under_review: 'Under Review',
  resolved: 'Resolved',
}

const requestStatusLabels = {
  violation_notice_issued: 'Violation Notice Issued',
  follow_up_requested: 'Follow-Up Requested',
  clearance_approved: 'Clearance Approved',
}

function statusVariant(status) {
  if (status === 'active') {
    return 'default'
  }

  if (status === 'pending') {
    return 'secondary'
  }

  return 'outline'
}

function certificateVariant(status) {
  if (status === 'active') {
    return 'default'
  }

  if (status === 'revoked') {
    return 'destructive'
  }

  if (status === 'expired') {
    return 'outline'
  }

  return 'secondary'
}

function violationSeverityVariant(status) {
  if (status === 'major') {
    return 'destructive'
  }

  if (status === 'moderate') {
    return 'secondary'
  }

  return 'outline'
}

function violationStatusVariant(status) {
  if (status === 'open') {
    return 'destructive'
  }

  if (status === 'resolved') {
    return 'default'
  }

  return 'secondary'
}

function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(value.startsWith('20') && value.length === 10 ? `${value}T00:00:00` : value)

  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function formatDateTime(value) {
  if (!value) {
    return '—'
  }

  return new Date(value).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

export default function EstablishmentProfilePage() {
  const { id } = useParams()
  const { user } = useAuth()

  const canWrite = ['administrator', 'barangay_staff'].includes(
    user?.role?.slug,
  )
  const [dialogOpen, setDialogOpen] = useState(false)
  const [form, setForm] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)

  const profileQuery = useQuery({
    queryKey: ['establishment-profile', id],
    queryFn: async () => fetchEstablishmentProfile(id),
    placeholderData: keepPreviousData,
  })

  const data = profileQuery.data?.data

  useEffect(() => {
    if (profileQuery.isError) {
      toast.error('Unable to load establishment profile')
      console.error(profileQuery.error)
    }
  }, [profileQuery.error, profileQuery.isError])

  const establishment = useMemo(() => data?.establishment ?? null, [data])
  const summary = useMemo(() => data?.summary ?? {}, [data])
  const inspections = useMemo(
    () =>
      Array.isArray(data?.inspections)
        ? data.inspections
        : Array.isArray(data?.inspections?.data)
          ? data.inspections.data
          : [],
    [data],
  )
  const schedules = useMemo(() => normalizeArray(data?.inspection_schedules), [data])
  const violations = useMemo(() => normalizeArray(data?.violations), [data])
  const documents = useMemo(() => normalizeArray(data?.documents), [data])
  const certifications = useMemo(() => normalizeArray(data?.certifications), [data])
  const clearances = useMemo(() => normalizeArray(data?.clearances), [data])
  const followUps = useMemo(() => normalizeArray(data?.follow_ups), [data])
  const activity = useMemo(() => normalizeArray(data?.activity), [data])

  const category = ESTABLISHMENT_CATEGORIES.find(
    (item) => item.value === establishment?.category,
  )

  const loading =
    profileQuery.isLoading && !establishment

  function openEditDialog() {
    if (!establishment) {
      return
    }

    setForm({
      name: establishment.name ?? '',
      category: establishment.category ?? 'food_establishment',
      business_type: establishment.business_type ?? '',
      owner_name: establishment.owner_name ?? '',
      address: establishment.address ?? '',
      contact_number: establishment.contact_number ?? '',
      email: establishment.email ?? '',
      registration_number: establishment.registration_number ?? '',
      status: establishment.status ?? 'pending',
      latitude: establishment.latitude ?? '',
      longitude: establishment.longitude ?? '',
    })
    setErrors({})
    setDialogOpen(true)
  }

  function updateForm(key, value) {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setErrors({})

    try {
      await updateEstablishment(establishment.id, {
        ...form,
        latitude: form.latitude === '' ? null : form.latitude,
        longitude: form.longitude === '' ? null : form.longitude,
      })
      toast.success('Establishment details updated')
      setDialogOpen(false)
      await profileQuery.refetch()
    } catch (error) {
      const validationErrors = error.response?.data?.errors

      if (validationErrors) {
        setErrors(validationErrors)
        toast.error('Please review the highlighted fields')
      } else {
        toast.error('Unable to save establishment')
      }

      console.error(error)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex items-start gap-3">
          <Button
            variant="ghost"
            size="icon-sm"
            nativeButton={false}
            render={<Link to="/establishments" />}
            aria-label="Back to establishments"
            className="mt-1"
          >
            <ArrowLeft className="size-4" />
          </Button>
          <div>
            {profileQuery.isLoading ? (
              <>
                <Skeleton className="h-8 w-64" />
                <Skeleton className="mt-2 h-4 w-40" />
              </>
            ) : (
              <>
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="text-2xl font-semibold tracking-tight">
                    {establishment?.name}
                  </h2>
                  <Badge variant="secondary">
                    {category?.label ??
                      establishment?.category_label ??
                      establishment?.category}
                  </Badge>
                  <Badge variant={statusVariant(establishment?.status)}>
                    {statusLabels[establishment?.status] ??
                      establishment?.status}
                  </Badge>
                </div>
                <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                  <MapPin className="size-3.5" />
                  {establishment?.address ?? '—'} ·{' '}
                  {establishment?.registration_number ?? 'No registration'}
                </p>
              </>
            )}
          </div>
        </div>

        {canWrite && !loading && (
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={openEditDialog}>
              <Edit className="size-4" />
              Edit Details
            </Button>
            <Button
              nativeButton={false}
              render={<Link to="/inspections" />}
            >
              <CalendarPlus className="size-4" />
              Schedule Inspection
            </Button>
          </div>
        )}
      </div>

      {loading ? (
        <div className="space-y-4">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      ) : !establishment ? (
        <Card>
          <CardContent className="py-10 text-center">
            <Building2 className="mx-auto size-8 text-muted-foreground/40" />
            <p className="mt-3 text-sm font-medium">
              Establishment not found
            </p>
            <p className="mt-1 text-sm text-muted-foreground">
              It may have been archived or removed.
            </p>
            <Button variant="outline" size="sm" className="mt-4" nativeButton={false} render={<Link to="/establishments" />}>
              <ArrowLeft className="size-4" />
              Back to All Establishments
            </Button>
          </CardContent>
        </Card>
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <Metric icon={ClipboardCheck} label="Inspections" value={summary.inspections_count ?? 0} />
            <Metric icon={Gavel} label="Open Violations" value={summary.open_violations_count ?? 0} />
            <Metric icon={FileText} label="Documents" value={summary.documents_count ?? 0} />
            <Metric icon={Award} label="Certificates" value={summary.certifications_count ?? 0} />
            <Metric icon={CheckCircle2} label="Clearances" value={summary.clearances_count ?? 0} />
            <Metric icon={CalendarDays} label="Last Inspection" value={summary.last_inspection_date ? formatDate(summary.last_inspection_date) : '—'} />
          </div>

          <Tabs defaultValue="overview">
            <TabsList className="flex h-auto flex-wrap">
              <TabsTrigger value="overview">Overview</TabsTrigger>
              <TabsTrigger value="documents">Documents / OCR</TabsTrigger>
              <TabsTrigger value="inspections">Inspections</TabsTrigger>
              <TabsTrigger value="violations">Violations</TabsTrigger>
              <TabsTrigger value="certificates">Certificates & Clearances</TabsTrigger>
              <TabsTrigger value="activity">Activity</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" className="space-y-4">
              <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <Store className="size-4 text-muted-foreground" />
                      Basic Information
                    </CardTitle>
                    <CardDescription>Core establishment details</CardDescription>
                  </CardHeader>
                  <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                    <Detail label="Establishment name" value={establishment.name} />
                    <Detail label="Business type" value={establishment.business_type || establishment.category_label} />
                    <Detail label="Category" value={category?.label ?? establishment.category} />
                    <Detail label="Registration number" value={establishment.registration_number} />
                    <Detail label="Barangay" value={establishment.barangay} />
                    <Detail label="Registered on" value={formatDateTime(establishment.created_at)} />
                    <Detail label="Last updated" value={formatDateTime(establishment.updated_at)} />
                    <Detail label="Registry status" value={statusLabels[establishment.status] ?? establishment.status} />
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <UserIcon className="size-4 text-muted-foreground" />
                      Owner / Operator
                    </CardTitle>
                    <CardDescription>Person responsible for the establishment</CardDescription>
                  </CardHeader>
                  <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                    <Detail label="Owner / operator" value={establishment.owner_name} />
                    <Detail
                      label="Linked resident"
                      value={establishment.resident?.name ?? 'No linked resident'}
                    />
                    <Detail label="Contact number" value={establishment.contact_number} />
                    <Detail label="Email" value={establishment.email} />
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <MapPin className="size-4 text-muted-foreground" />
                      Address / Location
                    </CardTitle>
                    <CardDescription>Where the establishment operates</CardDescription>
                  </CardHeader>
                  <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                    <div className="sm:col-span-2">
                      <Detail label="Address" value={establishment.address} />
                    </div>
                    <Detail label="Barangay" value={establishment.barangay} />
                    <Detail
                      label="Coordinates"
                      value={
                        establishment.latitude != null
                          ? `${establishment.latitude}, ${establishment.longitude ?? '—'}`
                          : 'Not set'
                      }
                    />
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <Link2 className="size-4 text-muted-foreground" />
                      Registration / Application
                    </CardTitle>
                    <CardDescription>Application records tied to this establishment</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <Detail
                      label="Registration number"
                      value={establishment.registration_number ?? '—'}
                    />
                    <Detail label="Category" value={category?.label ?? establishment.category} />
                    <Detail
                      label="Ownership status"
                      value={establishment.ownership_status ?? 'Not set'}
                    />
                    <div>
                      <Label className="text-xs text-muted-foreground">
                        Recent applications
                      </Label>
                      <div className="mt-2 space-y-2">
                        {followUps.length === 0 ? (
                          <p className="text-sm text-muted-foreground">
                            No application records linked to this establishment yet.
                          </p>
                        ) : (
                          followUps.slice(0, 3).map((request) => (
                            <div
                              key={request.id}
                              className="rounded-lg border border-border p-3"
                            >
                              <div className="flex items-start justify-between gap-3">
                                <div>
                                  <p className="text-sm font-medium">
                                    {request.request_number}
                                  </p>
                                  <p className="text-xs text-muted-foreground">
                                    {request.inspection_category?.name ?? 'Application'} ·{' '}
                                    {request.application_type?.name ?? ''}
                                  </p>
                                </div>
                                <Badge variant="secondary">
                                  {requestStatusLabels[request.status] ??
                                    request.status}
                                </Badge>
                              </div>
                            </div>
                          ))
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>

            <TabsContent value="documents" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <FileText className="size-4 text-muted-foreground" />
                    Uploaded Documents
                  </CardTitle>
                  <CardDescription>
                    Documents uploaded for this establishment and their OCR status
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  {documents.length === 0 ? (
                    <EmptyState icon={FileText} text="No documents uploaded for this establishment yet." />
                  ) : (
                    <div className="overflow-x-auto">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Document</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Uploaded</TableHead>
                            <TableHead className="text-right">Action</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {documents.map((document) => (
                            <TableRow key={document.id}>
                              <TableCell>
                                <div className="font-medium">
                                  {document.original_name}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                  {document.uploader?.name ?? 'Unknown uploader'}
                                </div>
                              </TableCell>
                              <TableCell className="text-sm capitalize">
                                {document.document_type?.replace(/_/g, ' ')}
                              </TableCell>
                              <TableCell>
                                <Badge variant={documentStatusVariant(document.status)}>
                                  {documentStatusLabels[document.status] ?? document.status}
                                </Badge>
                              </TableCell>
                              <TableCell className="text-sm">
                                {formatDateTime(document.created_at)}
                              </TableCell>
                              <TableCell className="text-right">
                                <Button
                                  variant="outline"
                                  size="sm"
                                  nativeButton={false}
                                  render={<Link to={`/ocr-results/${document.id}`} />}
                                >
                                  <ScanText className="size-4" />
                                  View OCR
                                </Button>
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </div>
                  )}
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <ScanText className="size-4 text-muted-foreground" />
                    OCR Results
                  </CardTitle>
                  <CardDescription>
                    Extracted information from the establishment's documents
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  {documents.filter((document) => document.extraction).length === 0 ? (
                    <EmptyState icon={ScanText} text="No OCR results yet for this establishment's documents." />
                  ) : (
                    <div className="overflow-x-auto">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Document</TableHead>
                            <TableHead>Business Name</TableHead>
                            <TableHead>Owner</TableHead>
                            <TableHead>OCR Status</TableHead>
                            <TableHead className="text-right">Action</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {documents
                            .filter((document) => document.extraction)
                            .map((document) => {
                              const extraction = document.extraction

                              return (
                                <TableRow key={document.id}>
                                  <TableCell className="font-medium">
                                    {document.original_name}
                                  </TableCell>
                                  <TableCell>
                                    {extraction.business_name ?? '—'}
                                  </TableCell>
                                  <TableCell>{extraction.owner_name ?? '—'}</TableCell>
                                  <TableCell>
                                    <Badge variant={ocrStatusVariant(extraction.ocr_status)}>
                                      {extraction.ocr_status ?? 'pending'}
                                    </Badge>
                                  </TableCell>
                                  <TableCell className="text-right">
                                    <Button
                                      variant="outline"
                                      size="sm"
                                      nativeButton={false}
                                      render={<Link to={`/ocr-results/${document.id}`} />}
                                    >
                                      View Details
                                    </Button>
                                  </TableCell>
                                </TableRow>
                              )
                            })}
                        </TableBody>
                      </Table>
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="inspections" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <ClipboardCheck className="size-4 text-muted-foreground" />
                    Inspection History
                  </CardTitle>
                  <CardDescription>
                    Inspections conducted for this establishment
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  {inspections.length === 0 ? (
                    <EmptyState icon={ClipboardCheck} text="No inspections have been conducted yet." />
                  ) : (
                    <div className="overflow-x-auto">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Inspector</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Violations</TableHead>
                            <TableHead className="text-right">Action</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {inspections.map((inspection) => (
                            <TableRow key={inspection.id}>
                              <TableCell>
                                {formatDate(inspection.inspection_date)}
                              </TableCell>
                              <TableCell>
                                {inspection.inspector?.name ?? 'Unassigned'}
                              </TableCell>
                              <TableCell>
                                <Badge variant={inspectionStatusVariant(inspection.status)}>
                                  {inspectionStatusLabel(inspection.status)}
                                </Badge>
                              </TableCell>
                              <TableCell>{inspection.violations_count ?? 0}</TableCell>
                              <TableCell className="text-right">
                                <Button
                                  variant="outline"
                                  size="sm"
                                  nativeButton={false}
                                  render={<Link to="/inspections" />}
                                >
                                  View All
                                </Button>
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </div>
                  )}
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    Scheduled / Incoming Inspections
                  </CardTitle>
                  <CardDescription>Scheduled visits and follow-up inspections</CardDescription>
                </CardHeader>
                <CardContent>
                  {schedules.length === 0 && followUps.length === 0 ? (
                    <EmptyState icon={CalendarDays} text="No scheduled or follow-up inspections yet." />
                  ) : (
                    <div className="space-y-3">
                      {schedules.map((schedule) => (
                        <div key={schedule.id} className="rounded-lg border border-border p-3">
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <p className="text-sm font-medium">
                                {schedule.inspector?.name ?? 'Unassigned inspector'}
                              </p>
                              <p className="text-xs text-muted-foreground">
                                {formatDate(schedule.scheduled_date)} ·{' '}
                                {schedule.scheduled_time?.slice(0, 5) ?? 'No time set'}
                              </p>
                            </div>
                            <Badge variant="secondary">{schedule.status}</Badge>
                          </div>
                        </div>
                      ))}
                      {followUps.map((request) => (
                        <div key={request.id} className="rounded-lg border border-border p-3">
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <p className="text-sm font-medium">
                                {request.request_number}
                              </p>
                              <p className="text-xs text-muted-foreground">
                                {request.business_name ??
                                  request.applicant_name ??
                                  'Establishment application'}
                              </p>
                            </div>
                            <Badge variant="secondary">
                              <RefreshCw className="mr-1 size-3" />
                              {requestStatusLabels[request.status] ?? request.status}
                            </Badge>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="violations">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <ShieldAlert className="size-4 text-muted-foreground" />
                    Violations
                  </CardTitle>
                  <CardDescription>Violation notices recorded against this establishment</CardDescription>
                </CardHeader>
                <CardContent>
                  {violations.length === 0 ? (
                    <EmptyState icon={ShieldAlert} text="No violations recorded for this establishment." />
                  ) : (
                    <div className="overflow-x-auto">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Title</TableHead>
                            <TableHead>Severity</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Correction Deadline</TableHead>
                            <TableHead>Reported</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {violations.map((violation) => (
                            <TableRow key={violation.id}>
                              <TableCell>
                                <div className="font-medium">{violation.title}</div>
                                <div className="max-w-[280px] text-xs text-muted-foreground line-clamp-2">
                                  {violation.description}
                                </div>
                              </TableCell>
                              <TableCell>
                                <Badge variant={violationSeverityVariant(violation.severity)}>
                                  {violationSeverityLabels[violation.severity] ?? violation.severity}
                                </Badge>
                              </TableCell>
                              <TableCell>
                                <Badge variant={violationStatusVariant(violation.status)}>
                                  {violationStatusLabels[violation.status] ?? violation.status}
                                </Badge>
                              </TableCell>
                              <TableCell>
                                {formatDate(violation.correction_deadline)}
                              </TableCell>
                              <TableCell>
                                {violation.reporter?.name ?? '—'}
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="certificates" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Award className="size-4 text-muted-foreground" />
                    Certifications
                  </CardTitle>
                  <CardDescription>Certificates issued for this establishment</CardDescription>
                </CardHeader>
                <CardContent>
                  {certifications.length === 0 ? (
                    <EmptyState icon={Award} text="No certification issued yet." />
                  ) : (
                    <div className="overflow-x-auto">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Certificate No.</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Issue Date</TableHead>
                            <TableHead>Expiration</TableHead>
                            <TableHead>Status</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {certifications.map((certificate) => (
                            <TableRow key={certificate.id}>
                              <TableCell className="font-mono text-xs">
                                {certificate.number}
                              </TableCell>
                              <TableCell>{certificate.document_type}</TableCell>
                              <TableCell>{formatDate(certificate.issue_date)}</TableCell>
                              <TableCell>{formatDate(certificate.expiration_date)}</TableCell>
                              <TableCell>
                                <Badge variant={certificateVariant(certificate.status)}>
                                  {certificateStatusLabels[certificate.status] ?? certificate.status}
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </div>
                  )}
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <CheckCircle2 className="size-4 text-muted-foreground" />
                    Clearances
                  </CardTitle>
                  <CardDescription>Health and safety clearances issued for this establishment</CardDescription>
                </CardHeader>
                <CardContent>
                  {clearances.length === 0 ? (
                    <EmptyState icon={CheckCircle2} text="No clearance issued yet." />
                  ) : (
                    <div className="overflow-x-auto">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Clearance No.</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Issue Date</TableHead>
                            <TableHead>Expiration</TableHead>
                            <TableHead>Status</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {clearances.map((clearance) => (
                            <TableRow key={clearance.id}>
                              <TableCell className="font-mono text-xs">
                                {clearance.number}
                              </TableCell>
                              <TableCell>{clearance.document_type}</TableCell>
                              <TableCell>{formatDate(clearance.issue_date)}</TableCell>
                              <TableCell>{formatDate(clearance.expiration_date)}</TableCell>
                              <TableCell>
                                <Badge variant={certificateVariant(clearance.status)}>
                                  {certificateStatusLabels[clearance.status] ?? clearance.status}
                                </Badge>
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="activity">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <RefreshCw className="size-4 text-muted-foreground" />
                    Activity / History
                  </CardTitle>
                  <CardDescription>Recent activity linked to this establishment</CardDescription>
                </CardHeader>
                <CardContent>
                  {activity.length === 0 ? (
                    <EmptyState icon={RefreshCw} text="No recorded activity yet for this establishment." />
                  ) : (
                    <div className="relative space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-px before:bg-border">
                      {activity.map((entry, index) => (
                        <div key={index} className="relative pl-8">
                          <span
                            className={cn(
                              'absolute left-0 top-1 flex size-4 items-center justify-center rounded-full ring-4 ring-background',
                              activityDot(entry.type),
                            )}
                          />
                          <p className="text-sm font-medium">{entry.label}</p>
                          <p className="text-xs text-muted-foreground">{entry.detail}</p>
                          <p className="text-xs text-muted-foreground/70">
                            {formatDateTime(entry.at)}
                          </p>
                        </div>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>Edit Establishment</DialogTitle>
            <DialogDescription>
              Keep registry details accurate for inspection scheduling and reporting.
            </DialogDescription>
          </DialogHeader>

          {form && (
            <form className="space-y-4" onSubmit={handleSubmit}>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field
                  label="Establishment name"
                  value={form.name}
                  error={errors.name}
                  onChange={(value) => updateForm('name', value)}
                />
                <div className="space-y-2">
                  <Label>Category</Label>
                  <select
                    className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                    value={form.category}
                    onChange={(event) => updateForm('category', event.target.value)}
                  >
                    {ESTABLISHMENT_CATEGORIES.map((category) => (
                      <option key={category.value} value={category.value}>
                        {category.label}
                      </option>
                    ))}
                  </select>
                  {errors.category && (
                    <p className="text-xs text-destructive">{errors.category[0]}</p>
                  )}
                </div>
                <Field
                  label="Business type"
                  value={form.business_type}
                  error={errors.business_type}
                  required={false}
                  onChange={(value) => updateForm('business_type', value)}
                />
                <Field
                  label="Owner / operator name"
                  value={form.owner_name}
                  error={errors.owner_name}
                  onChange={(value) => updateForm('owner_name', value)}
                />
                <Field
                  label="Registration number"
                  value={form.registration_number}
                  error={errors.registration_number}
                  onChange={(value) => updateForm('registration_number', value)}
                />
                <Field
                  label="Contact number"
                  value={form.contact_number}
                  error={errors.contact_number}
                  required={false}
                  onChange={(value) => updateForm('contact_number', value)}
                />
                <Field
                  label="Email"
                  type="email"
                  value={form.email}
                  error={errors.email}
                  required={false}
                  onChange={(value) => updateForm('email', value)}
                />
                <div className="space-y-2">
                  <Label>Status</Label>
                  <select
                    className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                    value={form.status}
                    onChange={(event) => updateForm('status', event.target.value)}
                  >
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                  </select>
                  {errors.status && (
                    <p className="text-xs text-destructive">{errors.status[0]}</p>
                  )}
                </div>
              </div>

              <div className="space-y-2">
                <Label>Address</Label>
                <textarea
                  className="min-h-20 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  value={form.address}
                  onChange={(event) => updateForm('address', event.target.value)}
                  required
                />
                {errors.address && (
                  <p className="text-xs text-destructive">{errors.address[0]}</p>
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
                  {submitting ? 'Saving...' : 'Save Changes'}
                </Button>
              </DialogFooter>
            </form>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}

function normalizeArray(value) {
  if (Array.isArray(value)) {
    return value
  }

  if (Array.isArray(value?.data)) {
    return value.data
  }

  return []
}

function Metric({ icon: Icon, label, value }) {
  return (
    <div className="rounded-lg border border-border p-3">
      <div className="flex items-center gap-2">
        <Icon className="size-4 text-muted-foreground" />
        <p className="text-xs text-muted-foreground">{label}</p>
      </div>
      <p className="mt-1 truncate text-xl font-semibold">{value}</p>
    </div>
  )
}

function Detail({ label, value }) {
  return (
    <div>
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="font-medium break-words">{value ?? 'N/A'}</p>
    </div>
  )
}

function EmptyState({ icon: Icon, text }) {
  return (
    <div className="py-10 text-center">
      <Icon className="mx-auto size-8 text-muted-foreground/40" />
      <p className="mt-3 text-sm text-muted-foreground">{text}</p>
    </div>
  )
}

function Field({ label, value, onChange, error, type = 'text', required = true }) {
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

function documentStatusVariant(status) {
  if (status === 'verified') {
    return 'default'
  }

  if (status === 'rejected') {
    return 'destructive'
  }

  return 'secondary'
}

function ocrStatusVariant(status) {
  if (status === 'verified') {
    return 'default'
  }

  if (status === 'failed' || status === 'error') {
    return 'destructive'
  }

  if (status === 'processed' || status === 'completed') {
    return 'secondary'
  }

  return 'outline'
}

function inspectionStatusVariant(status) {
  if (status === 'completed') {
    return 'default'
  }

  if (status === 'ongoing') {
    return 'secondary'
  }

  return 'outline'
}

function inspectionStatusLabel(status) {
  const labels = {
    scheduled: 'Scheduled',
    ongoing: 'Ongoing',
    completed: 'Completed',
    cancelled: 'Cancelled',
  }

  return labels[status] ?? status
}

function activityDot(type) {
  if (type === 'inspection') {
    return 'bg-blue-500'
  }

  if (type === 'document') {
    return 'bg-emerald-500'
  }

  return 'bg-muted'
}