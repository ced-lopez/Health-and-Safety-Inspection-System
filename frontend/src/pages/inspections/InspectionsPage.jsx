import { useEffect, useMemo, useState } from 'react'
import {
  CalendarPlus,
  ClipboardCheck,
  Edit,
  FileText,
  Printer,
  Save,
  Search,
  Trash2,
} from 'lucide-react'
import { toast } from 'sonner'

import { useAuth } from '@/context/AuthContext'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  createInspectionSchedule,
  deleteInspectionSchedule,
  fetchComplianceChecklist,
  fetchInspectionOptions,
  fetchInspectionReport,
  fetchInspectionSchedules,
  saveComplianceChecklist,
  updateInspectionReport,
  updateInspectionSchedule,
} from '@/services/inspectionService'

const emptyForm = {
  establishment_id: '',
  inspector_id: '',
  scheduled_date: '',
  scheduled_time: '',
  status: 'scheduled',
  notes: '',
}

const statusLabels = {
  scheduled: 'Scheduled',
  ongoing: 'Ongoing',
  completed: 'Completed',
  cancelled: 'Cancelled',
}

const complianceLabels = {
  compliant: 'Compliant',
  non_compliant: 'Non-Compliant',
  needs_correction: 'Needs Correction',
}

const reportStatusLabels = {
  compliant: 'Compliant',
  non_compliant: 'Non-Compliant',
  needs_correction: 'Needs Correction',
}

const checklistCategoryLabels = {
  health_sanitation: 'Health and Sanitation',
  fire_safety: 'Fire Safety',
  workplace_safety: 'Workplace Safety',
}

function statusVariant(status) {
  if (status === 'completed') {
    return 'default'
  }

  if (status === 'ongoing') {
    return 'secondary'
  }

  return 'outline'
}

function formatDate(value) {
  if (!value) {
    return 'Not set'
  }

  return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function toDateInput(date) {
  if (!date) {
    return ''
  }

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

export default function InspectionsPage() {
  const { user } = useAuth()
  const [schedules, setSchedules] = useState([])
  const [establishments, setEstablishments] = useState([])
  const [inspectors, setInspectors] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [filters, setFilters] = useState({
    search: '',
    status: 'all',
    inspector_id: 'all',
    page: 1,
  })
  const [searchInput, setSearchInput] = useState('')
  const [selectedDate, setSelectedDate] = useState(new Date())
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})
  const [checklistOpen, setChecklistOpen] = useState(false)
  const [checklistSchedule, setChecklistSchedule] = useState(null)
  const [checklistData, setChecklistData] = useState(null)
  const [checklistForm, setChecklistForm] = useState({})
  const [checklistLoading, setChecklistLoading] = useState(false)
  const [checklistSubmitting, setChecklistSubmitting] = useState(false)
  const [reportOpen, setReportOpen] = useState(false)
  const [reportSchedule, setReportSchedule] = useState(null)
  const [reportData, setReportData] = useState(null)
  const [reportForm, setReportForm] = useState({
    overall_assessment: '',
    recommendations: '',
  })
  const [reportLoading, setReportLoading] = useState(false)
  const [reportSubmitting, setReportSubmitting] = useState(false)

  const roleSlug = user?.role?.slug
  const canWrite = ['administrator', 'health_officer'].includes(roleSlug)
  const canArchive = roleSlug === 'administrator'

  const queryParams = useMemo(
    () => ({
      search: filters.search || undefined,
      status: filters.status,
      inspector_id: filters.inspector_id,
      page: filters.page,
      per_page: 10,
    }),
    [filters],
  )

  const selectedDateKey = toDateInput(selectedDate)
  const selectedDateSchedules = schedules.filter(
    (schedule) => schedule.scheduled_date === selectedDateKey,
  )

  async function loadOptions() {
    try {
      const response = await fetchInspectionOptions()
      setEstablishments(response.data.establishments ?? [])
      setInspectors(response.data.inspectors ?? [])
    } catch (error) {
      toast.error('Unable to load scheduling options')
      console.error(error)
    }
  }

  async function loadSchedules() {
    setLoading(true)

    try {
      const response = await fetchInspectionSchedules(queryParams)
      const registry = response.data.schedules ?? []
      setSchedules(registry.data ?? registry)
      setMeta(response.data.meta ?? { current_page: 1, last_page: 1, total: 0 })
    } catch (error) {
      toast.error('Unable to load inspection schedules')
      console.error(error)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadOptions()
  }, [])

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
    }, 300)

    return () => window.clearTimeout(timeout)
  }, [searchInput])

  useEffect(() => {
    loadSchedules()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [queryParams])

  function updateFilter(key, value) {
    setFilters((current) => ({
      ...current,
      [key]: value,
      page: key === 'page' ? value : 1,
    }))
  }

  function openCreateDialog() {
    setEditing(null)
    setForm({
      ...emptyForm,
      scheduled_date: toDateInput(selectedDate) || '',
    })
    setErrors({})
    setDialogOpen(true)
  }

  function openEditDialog(schedule) {
    setEditing(schedule)
    setForm({
      establishment_id: String(schedule.establishment_id ?? ''),
      inspector_id: String(schedule.inspector_id ?? ''),
      scheduled_date: schedule.scheduled_date ?? '',
      scheduled_time: schedule.scheduled_time?.slice(0, 5) ?? '',
      status: schedule.status ?? 'scheduled',
      notes: schedule.notes ?? '',
    })
    setErrors({})
    setDialogOpen(true)
  }

  function updateForm(key, value) {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: undefined }))
  }

  function normalizePayload() {
    return {
      ...form,
      establishment_id: Number(form.establishment_id),
      inspector_id: Number(form.inspector_id),
      scheduled_time: form.scheduled_time || null,
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setErrors({})

    try {
      if (editing) {
        await updateInspectionSchedule(editing.id, normalizePayload())
        toast.success('Inspection schedule updated')
      } else {
        await createInspectionSchedule(normalizePayload())
        toast.success('Inspection scheduled')
      }

      setDialogOpen(false)
      await loadSchedules()
    } catch (error) {
      const validationErrors = error.response?.data?.errors

      if (validationErrors) {
        setErrors(validationErrors)
        toast.error('Please review the highlighted fields')
      } else {
        toast.error('Unable to save inspection schedule')
      }

      console.error(error)
    } finally {
      setSubmitting(false)
    }
  }

  async function handleArchive(schedule) {
    const confirmed = window.confirm(
      `Archive schedule for ${schedule.establishment?.name ?? 'this establishment'}?`,
    )

    if (!confirmed) {
      return
    }

    try {
      await deleteInspectionSchedule(schedule.id)
      toast.success('Inspection schedule archived')
      await loadSchedules()
    } catch (error) {
      toast.error('Unable to archive inspection schedule')
      console.error(error)
    }
  }

  async function openChecklistDialog(schedule) {
    setChecklistSchedule(schedule)
    setChecklistOpen(true)
    setChecklistLoading(true)

    try {
      const response = await fetchComplianceChecklist(schedule.id)
      const checklists = response.data.checklists ?? []
      const results = response.data.results ?? []
      const resultMap = Object.fromEntries(
        results.map((result) => [
          result.checklist_item_id,
          {
            compliance_status: result.compliance_status,
            remarks: result.remarks ?? '',
            files: [],
          },
        ]),
      )
      const nextForm = {}

      checklists.forEach((checklist) => {
        ;(checklist.items ?? []).forEach((item) => {
          nextForm[item.id] = resultMap[item.id] ?? {
            compliance_status: 'compliant',
            remarks: '',
            files: [],
          }
        })
      })

      setChecklistData(response.data)
      setChecklistForm(nextForm)
    } catch (error) {
      toast.error('Unable to load compliance checklist')
      console.error(error)
    } finally {
      setChecklistLoading(false)
    }
  }

  function updateChecklistItem(itemId, key, value) {
    setChecklistForm((current) => ({
      ...current,
      [itemId]: {
        ...current[itemId],
        [key]: value,
      },
    }))
  }

  async function handleChecklistSubmit(event) {
    event.preventDefault()

    if (!checklistSchedule) {
      return
    }

    setChecklistSubmitting(true)

    try {
      const payload = new FormData()

      Object.entries(checklistForm).forEach(([itemId, result], index) => {
        payload.append(`results[${index}][checklist_item_id]`, itemId)
        payload.append(
          `results[${index}][compliance_status]`,
          result.compliance_status,
        )
        payload.append(`results[${index}][remarks]`, result.remarks ?? '')

        ;(result.files ?? []).forEach((file) => {
          payload.append(`evidence_files[${itemId}][]`, file)
        })
      })

      await saveComplianceChecklist(checklistSchedule.id, payload)
      toast.success('Compliance checklist saved')
      setChecklistOpen(false)
    } catch (error) {
      toast.error('Unable to save compliance checklist')
      console.error(error)
    } finally {
      setChecklistSubmitting(false)
    }
  }

  async function openReportDialog(schedule) {
    setReportSchedule(schedule)
    setReportOpen(true)
    setReportLoading(true)

    try {
      const response = await fetchInspectionReport(schedule.id)
      setReportData(response.data)
      setReportForm({
        overall_assessment: response.data.overall_assessment ?? '',
        recommendations: response.data.recommendations ?? '',
      })
    } catch (error) {
      toast.error('Unable to load inspection report')
      console.error(error)
    } finally {
      setReportLoading(false)
    }
  }

  async function handleReportSave() {
    if (!reportSchedule) {
      return
    }

    setReportSubmitting(true)

    try {
      const response = await updateInspectionReport(reportSchedule.id, reportForm)
      setReportData(response.data)
      toast.success('Inspection report updated')
    } catch (error) {
      toast.error('Unable to update inspection report')
      console.error(error)
    } finally {
      setReportSubmitting(false)
    }
  }

  function handlePrintReport() {
    window.print()
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold tracking-tight">Inspections</h2>
          <p className="text-sm text-muted-foreground">
            Schedule inspections, assign inspectors, and track inspection status
          </p>
        </div>
        {canWrite && (
          <Button onClick={openCreateDialog}>
            <CalendarPlus className="size-4" />
            Schedule Inspection
          </Button>
        )}
      </div>

      <Tabs defaultValue="list">
        <TabsList>
          <TabsTrigger value="list">List View</TabsTrigger>
          <TabsTrigger value="calendar">Calendar View</TabsTrigger>
        </TabsList>
        <TabsContent value="list">
          <Card>
            <CardHeader>
              <CardTitle>Inspection Schedules</CardTitle>
              <CardDescription>
                Manage appointments, inspector assignments, and schedule status
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-3 lg:grid-cols-[1fr_180px_220px]">
                <div className="relative">
                  <Search className="absolute left-2.5 top-2 size-4 text-muted-foreground" />
                  <Input
                    className="pl-8"
                    placeholder="Search establishment or registration no."
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
                  value={filters.inspector_id}
                  onChange={(event) => updateFilter('inspector_id', event.target.value)}
                >
                  <option value="all">All inspectors</option>
                  {inspectors.map((inspector) => (
                    <option key={inspector.id} value={inspector.id}>
                      {inspector.name}
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
              ) : schedules.length === 0 ? (
                <p className="py-8 text-center text-sm text-muted-foreground">
                  No inspection schedules found.
                </p>
              ) : (
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Establishment</TableHead>
                        <TableHead>Inspector</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {schedules.map((schedule) => (
                        <ScheduleRow
                          key={schedule.id}
                          schedule={schedule}
                          canWrite={canWrite}
                          canArchive={canArchive}
                          onEdit={openEditDialog}
                          onChecklist={openChecklistDialog}
                          onReport={openReportDialog}
                          onArchive={handleArchive}
                        />
                      ))}
                    </TableBody>
                  </Table>
                </div>
              )}

              <div className="flex flex-col gap-3 border-t pt-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>{meta.total} schedules</span>
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
        </TabsContent>
        <TabsContent value="calendar">
          <div className="grid gap-4 lg:grid-cols-[360px_1fr]">
            <Card>
              <CardHeader>
                <CardTitle>Inspection Calendar</CardTitle>
                <CardDescription>Pick a date to review scheduled visits</CardDescription>
              </CardHeader>
              <CardContent>
                <Calendar
                  mode="single"
                  selected={selectedDate}
                  onSelect={(date) => date && setSelectedDate(date)}
                  className="w-full"
                />
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>{formatDate(selectedDateKey)}</CardTitle>
                <CardDescription>Schedules loaded in the current list page</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {selectedDateSchedules.length === 0 ? (
                  <p className="py-8 text-center text-sm text-muted-foreground">
                    No schedules on this date.
                  </p>
                ) : (
                  selectedDateSchedules.map((schedule) => (
                    <div
                      key={schedule.id}
                      className="rounded-lg border border-border p-3"
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="font-medium">{schedule.establishment?.name}</p>
                          <p className="text-sm text-muted-foreground">
                            {schedule.scheduled_time?.slice(0, 5) ?? 'No time'} -
                            {schedule.inspector?.name ?? 'Unassigned'}
                          </p>
                        </div>
                        <Badge variant={statusVariant(schedule.status)}>
                          {statusLabels[schedule.status] ?? schedule.status}
                        </Badge>
                      </div>
                    </div>
                  ))
                )}
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>
              {editing ? 'Edit Inspection Schedule' : 'Schedule Inspection'}
            </DialogTitle>
            <DialogDescription>
              Assign an inspector, establishment, date, time, and current status.
            </DialogDescription>
          </DialogHeader>

          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid gap-4 sm:grid-cols-2">
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
                label="Inspector"
                value={form.inspector_id}
                error={errors.inspector_id}
                onChange={(value) => updateForm('inspector_id', value)}
              >
                <option value="">Select inspector</option>
                {inspectors.map((inspector) => (
                  <option key={inspector.id} value={inspector.id}>
                    {inspector.name}
                  </option>
                ))}
              </SelectField>
              <Field
                label="Scheduled date"
                type="date"
                value={form.scheduled_date}
                error={errors.scheduled_date}
                onChange={(value) => updateForm('scheduled_date', value)}
              />
              <Field
                label="Scheduled time"
                type="time"
                value={form.scheduled_time}
                error={errors.scheduled_time}
                required={false}
                onChange={(value) => updateForm('scheduled_time', value)}
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
                className="min-h-20 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
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
                {submitting ? 'Saving...' : 'Save Schedule'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={checklistOpen} onOpenChange={setChecklistOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
          <DialogHeader>
            <DialogTitle>Compliance Checklist</DialogTitle>
            <DialogDescription>
              {checklistSchedule?.establishment?.name ?? 'Inspection'} assessment
            </DialogDescription>
          </DialogHeader>

          {checklistLoading ? (
            <div className="space-y-3">
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-24 w-full" />
              <Skeleton className="h-24 w-full" />
            </div>
          ) : (
            <form className="space-y-4" onSubmit={handleChecklistSubmit}>
              <Tabs defaultValue={checklistData?.checklists?.[0]?.category}>
                <TabsList className="flex h-auto flex-wrap">
                  {(checklistData?.checklists ?? []).map((checklist) => (
                    <TabsTrigger key={checklist.id} value={checklist.category}>
                      {checklistCategoryLabels[checklist.category] ?? checklist.name}
                    </TabsTrigger>
                  ))}
                </TabsList>

                {(checklistData?.checklists ?? []).map((checklist) => (
                  <TabsContent
                    key={checklist.id}
                    value={checklist.category}
                    className="space-y-3"
                  >
                    {(checklist.items ?? []).map((item) => (
                      <ChecklistItemCard
                        key={item.id}
                        item={item}
                        value={checklistForm[item.id]}
                        onChange={updateChecklistItem}
                      />
                    ))}
                  </TabsContent>
                ))}
              </Tabs>

              <DialogFooter>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setChecklistOpen(false)}
                >
                  Cancel
                </Button>
                <Button type="submit" disabled={checklistSubmitting}>
                  {checklistSubmitting ? 'Saving...' : 'Save Checklist'}
                </Button>
              </DialogFooter>
            </form>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={reportOpen} onOpenChange={setReportOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
          <DialogHeader>
            <DialogTitle>Inspection Report</DialogTitle>
            <DialogDescription>
              {reportSchedule?.establishment?.name ?? 'Generated inspection report'}
            </DialogDescription>
          </DialogHeader>

          {reportLoading ? (
            <div className="space-y-3">
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-40 w-full" />
              <Skeleton className="h-40 w-full" />
            </div>
          ) : reportData ? (
            <div className="space-y-4" id="inspection-report">
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <ReportMetric label="Items" value={reportData.summary?.total_items ?? 0} />
                <ReportMetric label="Compliant" value={reportData.summary?.compliant ?? 0} />
                <ReportMetric
                  label="Needs Correction"
                  value={reportData.summary?.needs_correction ?? 0}
                />
                <ReportMetric
                  label="Non-Compliant"
                  value={reportData.summary?.non_compliant ?? 0}
                />
              </div>

              <Card>
                <CardHeader>
                  <CardTitle>Official Details</CardTitle>
                  <CardDescription>
                    Generated {new Date(reportData.generated_at).toLocaleString()}
                  </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                  <Detail label="Establishment" value={reportData.establishment?.name} />
                  <Detail label="Owner" value={reportData.establishment?.owner_name} />
                  <Detail label="Inspector" value={reportData.inspector?.name} />
                  <Detail label="Inspection Date" value={formatDate(reportData.inspection_date)} />
                  <Detail
                    label="Registration No."
                    value={reportData.establishment?.registration_number}
                  />
                  <Detail label="Address" value={reportData.establishment?.address} />
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Final Assessment</CardTitle>
                  <CardDescription>
                    Add the inspection conclusion and recommendations
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="space-y-2">
                    <Label>Overall assessment</Label>
                    <textarea
                      className="min-h-20 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                      value={reportForm.overall_assessment}
                      onChange={(event) =>
                        setReportForm((current) => ({
                          ...current,
                          overall_assessment: event.target.value,
                        }))
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Recommendations</Label>
                    <textarea
                      className="min-h-20 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                      value={reportForm.recommendations}
                      onChange={(event) =>
                        setReportForm((current) => ({
                          ...current,
                          recommendations: event.target.value,
                        }))
                      }
                    />
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Checklist Results</CardTitle>
                  <CardDescription>
                    Compliance findings generated from the digital checklist
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="overflow-x-auto">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Requirement</TableHead>
                          <TableHead>Category</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead>Remarks</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {(reportData.results ?? []).map((result) => (
                          <TableRow key={result.id}>
                            <TableCell className="font-medium">
                              {result.item_title}
                            </TableCell>
                            <TableCell>{result.item_category}</TableCell>
                            <TableCell>
                              <Badge variant={statusVariantForCompliance(result.compliance_status)}>
                                {reportStatusLabels[result.compliance_status] ??
                                  result.compliance_status}
                              </Badge>
                            </TableCell>
                            <TableCell>{result.remarks ?? 'No remarks'}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Violations</CardTitle>
                  <CardDescription>Violations linked to this inspection</CardDescription>
                </CardHeader>
                <CardContent>
                  {(reportData.violations ?? []).length === 0 ? (
                    <p className="py-4 text-sm text-muted-foreground">
                      No violations recorded for this inspection.
                    </p>
                  ) : (
                    <div className="space-y-3">
                      {reportData.violations.map((violation) => (
                        <div
                          key={violation.id}
                          className="rounded-lg border border-border p-3"
                        >
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <p className="font-medium">{violation.title}</p>
                              <p className="text-sm text-muted-foreground">
                                {violation.description}
                              </p>
                            </div>
                            <Badge variant="outline">{violation.severity}</Badge>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>

              <DialogFooter>
                <Button variant="outline" type="button" onClick={handlePrintReport}>
                  <Printer className="size-4" />
                  Print / Save PDF
                </Button>
                <Button
                  type="button"
                  disabled={reportSubmitting}
                  onClick={handleReportSave}
                >
                  <Save className="size-4" />
                  {reportSubmitting ? 'Saving...' : 'Save Report'}
                </Button>
              </DialogFooter>
            </div>
          ) : (
            <p className="py-8 text-center text-sm text-muted-foreground">
              Report data is unavailable.
            </p>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}

function ScheduleRow({
  schedule,
  canWrite,
  canArchive,
  onEdit,
  onChecklist,
  onReport,
  onArchive,
}) {
  return (
    <TableRow>
      <TableCell>
        <div className="font-medium">{schedule.establishment?.name ?? 'Unknown'}</div>
        <div className="text-xs text-muted-foreground">
          {schedule.establishment?.registration_number ?? 'No registration'}
        </div>
      </TableCell>
      <TableCell>{schedule.inspector?.name ?? 'Unassigned'}</TableCell>
      <TableCell>
        <div>{formatDate(schedule.scheduled_date)}</div>
        <div className="text-xs text-muted-foreground">
          {schedule.scheduled_time?.slice(0, 5) ?? 'No time set'}
        </div>
      </TableCell>
      <TableCell>
        <Badge variant={statusVariant(schedule.status)}>
          {statusLabels[schedule.status] ?? schedule.status}
        </Badge>
      </TableCell>
      <TableCell>
        <div className="flex justify-end gap-2">
          <Button
            variant="outline"
            size="icon-sm"
            aria-label="Open checklist"
            onClick={() => onChecklist(schedule)}
          >
            <ClipboardCheck className="size-4" />
          </Button>
          <Button
            variant="outline"
            size="icon-sm"
            aria-label="Open report"
            onClick={() => onReport(schedule)}
          >
            <FileText className="size-4" />
          </Button>
          {canWrite && (
            <Button
              variant="outline"
              size="icon-sm"
              aria-label="Edit schedule"
              onClick={() => onEdit(schedule)}
            >
              <Edit className="size-4" />
            </Button>
          )}
          {canArchive && (
            <Button
              variant="destructive"
              size="icon-sm"
              aria-label="Archive schedule"
              onClick={() => onArchive(schedule)}
            >
              <Trash2 className="size-4" />
            </Button>
          )}
        </div>
      </TableCell>
    </TableRow>
  )
}

function statusVariantForCompliance(status) {
  if (status === 'compliant') {
    return 'default'
  }

  if (status === 'non_compliant') {
    return 'destructive'
  }

  return 'secondary'
}

function ReportMetric({ label, value }) {
  return (
    <div className="rounded-lg border border-border p-3">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="text-2xl font-semibold">{value}</p>
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

function ChecklistItemCard({ item, value, onChange }) {
  return (
    <div className="space-y-3 rounded-lg border border-border p-3">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p className="text-sm font-medium">{item.title}</p>
          <p className="text-xs text-muted-foreground">{item.category}</p>
        </div>
        {item.is_required && <Badge variant="outline">Required</Badge>}
      </div>

      <div className="grid gap-3 lg:grid-cols-[220px_1fr]">
        <div className="space-y-2">
          <Label>Status</Label>
          <select
            className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
            value={value?.compliance_status ?? 'compliant'}
            onChange={(event) =>
              onChange(item.id, 'compliance_status', event.target.value)
            }
          >
            {Object.entries(complianceLabels).map(([status, label]) => (
              <option key={status} value={status}>
                {label}
              </option>
            ))}
          </select>
        </div>

        <div className="space-y-2">
          <Label>Remarks</Label>
          <Input
            value={value?.remarks ?? ''}
            placeholder="Add inspection notes"
            onChange={(event) => onChange(item.id, 'remarks', event.target.value)}
          />
        </div>
      </div>

      <div className="space-y-2">
        <Label>Evidence images</Label>
        <Input
          type="file"
          accept="image/*"
          multiple
          onChange={(event) =>
            onChange(item.id, 'files', Array.from(event.target.files ?? []))
          }
        />
      </div>
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

function SelectField({ label, value, onChange, error, children }) {
  const id = label.toLowerCase().replaceAll(' ', '-')

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <select
        id={id}
        className="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
        value={value}
        required
        onChange={(event) => onChange(event.target.value)}
      >
        {children}
      </select>
      {error && <p className="text-xs text-destructive">{error[0]}</p>}
    </div>
  )
}
