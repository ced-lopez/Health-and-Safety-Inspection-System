import { useEffect, useMemo, useRef, useState } from 'react'
import {
  AlertTriangle,
  ArrowLeft,
  Building2,
  CalendarDays,
  CheckCircle2,
  ClipboardCheck,
  FileText,
  Inbox,
  Loader2,
  MapPin,
  MessageSquare,
  Phone,
  RefreshCw,
  Send,
  Upload,
  User,
  Users,
  X,
} from 'lucide-react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import api from '@/services/api'
import {
  fetchAssignmentChecklist,
  fetchAssignmentReport,
  saveAssignmentChecklist,
  startAssignment,
  submitAssignment,
  updateAssignmentReport,
} from '@/services/assignmentService'
import { createViolation, uploadViolationEvidence } from '@/services/violationService'
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
import { cn } from '@/lib/utils'

const STATUS_OPTIONS = [
  { value: 'all', label: 'All Statuses' },
  { value: 'assigned', label: 'Assigned' },
  { value: 'downloaded', label: 'Downloaded' },
  { value: 'in_progress', label: 'In Progress' },
]

function titleCase(value) {
  if (!value) {
    return '—'
  }

  return String(value).replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

function assignmentStatusVariant(status) {
  const value = String(status).toLowerCase()

  if (value === 'in_progress') {
    return 'secondary'
  }

  if (value === 'downloaded') {
    return 'default'
  }

  if (value === 'submitted') {
    return 'outline'
  }

  return 'outline'
}

function violationSeverityVariant(severity) {
  if (severity === 'major') {
    return 'destructive'
  }

  if (severity === 'moderate') {
    return 'secondary'
  }

  return 'outline'
}

function violationStatusVariant(status) {
  if (status === 'resolved') {
    return 'default'
  }

  if (status === 'under_review') {
    return 'secondary'
  }

  return 'destructive'
}

function EvidenceDropzone({ files, onChange, disabled }) {
  const inputRef = useRef(null)
  const [dragOver, setDragOver] = useState(false)

  return (
    <div
      className={cn(
        'cursor-pointer rounded-lg border border-dashed p-3 text-center transition-colors',
        dragOver ? 'border-primary bg-primary/5' : 'border-input',
        disabled && 'pointer-events-none opacity-50',
      )}
      onDragOver={(e) => {
        e.preventDefault()
        setDragOver(true)
      }}
      onDragLeave={() => setDragOver(false)}
      onDrop={(e) => {
        e.preventDefault()
        setDragOver(false)

        if (disabled) {
          return
        }

        const dropped = Array.from(e.dataTransfer.files ?? [])
        onChange([...(files ?? []), ...dropped])
      }}
      onClick={() => inputRef.current?.click()}
    >
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        multiple
        hidden
        onChange={(e) => {
          const picked = Array.from(e.target.files ?? [])
          onChange([...(files ?? []), ...picked])
          e.target.value = ''
        }}
      />
      <div className="flex flex-wrap items-center justify-center gap-2">
        <Upload className="size-4 text-muted-foreground" />
        <span className="text-xs text-muted-foreground">Drag &amp; drop photos or click to browse</span>
      </div>
      {files && files.length > 0 && (
        <div className="mt-2 flex flex-wrap justify-center gap-1.5">
          {files.map((file, index) => (
            <span
              key={`${file.name}-${index}`}
              className="inline-flex max-w-full items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[0.7rem]"
            >
              <span className="truncate">{file.name}</span>
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation()
                  onChange(files.filter((_, i) => i !== index))
                }}
                className="text-muted-foreground hover:text-foreground"
              >
                <X className="size-3" />
              </button>
            </span>
          ))}
        </div>
      )}
    </div>
  )
}

function DetailRow({ icon: Icon, label, value }) {
  return (
    <div className="flex items-start gap-2">
      <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
      <div className="min-w-0">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="text-sm font-medium">{value || '—'}</p>
      </div>
    </div>
  )
}

function StatCard({ title, value, description, icon: Icon, loading, isError }) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        <Icon className="size-4 text-muted-foreground" />
      </CardHeader>
      <CardContent>
        {loading ? (
          <div className="space-y-2">
            <Skeleton className="h-9 w-16" />
            <Skeleton className="h-4 w-28" />
          </div>
        ) : (
          <>
            <div className="text-3xl font-bold">{isError ? '--' : (value ?? 0)}</div>
            <p className="text-xs text-muted-foreground">{description}</p>
          </>
        )}
      </CardContent>
    </Card>
  )
}

export default function InspectorDashboardPage() {
  const { user } = useAuth()
  const queryClient = useQueryClient()

  const [tab, setTab] = useState('assigned')
  const [statusFilter, setStatusFilter] = useState('all')
  const [categoryFilter, setCategoryFilter] = useState('all')
  const [dateFilter, setDateFilter] = useState('')
  const [searchTerm, setSearchTerm] = useState('')
  const [historySearch, setHistorySearch] = useState('')
  const [selectedId, setSelectedId] = useState(null)
  const [violationOpen, setViolationOpen] = useState(false)
  const [submitOpen, setSubmitOpen] = useState(false)

  const [results, setResults] = useState({})
  const [itemRemarks, setItemRemarks] = useState({})
  const [evidence, setEvidence] = useState({})
  const [assessment, setAssessment] = useState('')
  const [recommendations, setRecommendations] = useState('')
  const [inspectorNotes, setInspectorNotes] = useState('')

  const [violationForm, setViolationForm] = useState({
    title: '',
    description: '',
    severity: 'minor',
    status: 'open',
    correction_deadline: '',
  })
  const [violationFiles, setViolationFiles] = useState([])

  const dashboardQuery = useQuery({
    queryKey: ['inspector-dashboard'],
    queryFn: async () => {
      const response = await api.get('/v1/dashboard')
      return response.data.data
    },
  })

  const data = dashboardQuery.data
  const loading = dashboardQuery.isLoading && !data
  const isError = dashboardQuery.isError

  useEffect(() => {
    if (dashboardQuery.isError) {
      toast.error('Failed to load dashboard metrics')
      console.error(dashboardQuery.error)
    }
  }, [dashboardQuery.error, dashboardQuery.isError])

  const selected = useMemo(
    () => (data?.assigned_inspections ?? []).find((assignment) => assignment.id === selectedId) ?? null,
    [data, selectedId],
  )

  const checklistQuery = useQuery({
    queryKey: ['assignment-checklist', selectedId],
    queryFn: () => fetchAssignmentChecklist(selectedId).then((body) => body.data),
    enabled: Boolean(selectedId),
  })

  const reportQuery = useQuery({
    queryKey: ['assignment-report', selectedId],
    queryFn: () => fetchAssignmentReport(selectedId).then((body) => body.data),
    enabled: Boolean(selectedId),
  })

  useEffect(() => {
    if (checklistQuery.data) {
      const nextResults = {}
      const nextRemarks = {}

      for (const result of checklistQuery.data.results ?? []) {
        nextResults[result.checklist_item_id] = result.compliance_status
        nextRemarks[result.checklist_item_id] = result.remarks ?? ''
      }

      setResults(nextResults)
      setItemRemarks(nextRemarks)
    }
  }, [checklistQuery.data])

  useEffect(() => {
    if (reportQuery.data) {
      setAssessment(reportQuery.data.overall_assessment ?? '')
      setRecommendations(reportQuery.data.recommendations ?? '')
    }
  }, [reportQuery.data])

  const refreshAfterChange = () => {
    queryClient.invalidateQueries({ queryKey: ['inspector-dashboard'] })
    queryClient.invalidateQueries({ queryKey: ['assignment-checklist', selectedId] })
    queryClient.invalidateQueries({ queryKey: ['assignment-report', selectedId] })
  }

  const startMutation = useMutation({
    mutationFn: () => startAssignment(selectedId),
    onSuccess: () => {
      toast.success('Inspection started')
      refreshAfterChange()
    },
    onError: () => toast.error('Failed to start inspection'),
  })

  const submitMutation = useMutation({
    mutationFn: () => submitAssignment(selectedId, { notes: inspectorNotes }),
    onSuccess: () => {
      toast.success('Inspection submitted for review')
      setSubmitOpen(false)
      setSelectedId(null)
      queryClient.invalidateQueries({ queryKey: ['inspector-dashboard'] })
    },
    onError: () => toast.error('Failed to submit inspection'),
  })

  const saveChecklistMutation = useMutation({
    mutationFn: async () => {
      const formData = new FormData()
      const entries = Object.entries(results).filter(([, status]) => Boolean(status))
      let index = 0

      for (const [itemId, status] of entries) {
        formData.append(`results[${index}][checklist_item_id]`, itemId)
        formData.append(`results[${index}][compliance_status]`, status)
        formData.append(`results[${index}][remarks]`, itemRemarks[itemId] ?? '')

        for (const file of evidence[itemId] ?? []) {
          formData.append(`evidence_files[${itemId}][]`, file)
        }

        index += 1
      }

      return saveAssignmentChecklist(selectedId, formData)
    },
    onSuccess: () => {
      toast.success('Compliance checklist saved')
      setEvidence({})
      queryClient.invalidateQueries({ queryKey: ['assignment-checklist', selectedId] })
      queryClient.invalidateQueries({ queryKey: ['assignment-report', selectedId] })
    },
    onError: () => toast.error('Failed to save compliance checklist'),
  })

  const saveReportMutation = useMutation({
    mutationFn: (payload) => updateAssignmentReport(selectedId, payload),
    onSuccess: () => {
      toast.success('Inspection report saved')
      queryClient.invalidateQueries({ queryKey: ['assignment-report', selectedId] })
      queryClient.invalidateQueries({ queryKey: ['inspector-dashboard'] })
    },
    onError: () => toast.error('Failed to save inspection report'),
  })

  const createViolationMutation = useMutation({
    mutationFn: async (payload) => {
      const body = await createViolation(payload)
      const violation = body.data

      if (violationFiles.length > 0) {
        const evidenceForm = new FormData()
        evidenceForm.append('evidence_type', 'initial')

        for (const file of violationFiles) {
          evidenceForm.append('files[]', file)
        }

        await uploadViolationEvidence(violation.id, evidenceForm)
      }

      return violation
    },
    onSuccess: () => {
      toast.success('Violation recorded')
      setViolationOpen(false)
      setViolationFiles([])
      setViolationForm({
        title: '',
        description: '',
        severity: 'minor',
        status: 'open',
        correction_deadline: '',
      })
      queryClient.invalidateQueries({ queryKey: ['assignment-report', selectedId] })
      queryClient.invalidateQueries({ queryKey: ['inspector-dashboard'] })
    },
    onError: () => toast.error('Failed to record violation'),
  })

  const statsList = [
    {
      title: 'Assigned',
      value: data?.stats?.assigned,
      description: 'Awaiting inspection',
      icon: Inbox,
    },
    {
      title: 'In Progress',
      value: data?.stats?.in_progress,
      description: 'Currently being inspected',
      icon: ClipboardCheck,
    },
    {
      title: 'Completed',
      value: data?.stats?.completed,
      description: 'Year to date',
      icon: CheckCircle2,
    },
    {
      title: 'Follow-ups',
      value: data?.stats?.follow_ups,
      description: 'Requiring attention',
      icon: RefreshCw,
    },
    {
      title: 'Pending Sync',
      value: data?.stats?.pending_sync,
      description: 'Unsynced mobile records',
      icon: AlertTriangle,
    },
  ]

  const categories = useMemo(
    () =>
      Array.from(
        new Set((data?.assigned_inspections ?? []).map((assignment) => assignment.category).filter(Boolean)),
      ),
    [data],
  )

  const filteredAssignments = useMemo(() => {
    return (data?.assigned_inspections ?? []).filter((assignment) => {
      if (statusFilter !== 'all' && assignment.status !== statusFilter) {
        return false
      }

      if (categoryFilter !== 'all' && assignment.category !== categoryFilter) {
        return false
      }

      if (dateFilter && assignment.assigned_date !== dateFilter) {
        return false
      }

      if (searchTerm) {
        const haystack = `${assignment.business_name} ${assignment.applicant_name} ${assignment.request_number} ${assignment.establishment_name}`.toLowerCase()

        if (!haystack.includes(searchTerm.toLowerCase())) {
          return false
        }
      }

      return true
    })
  }, [data, statusFilter, categoryFilter, dateFilter, searchTerm])

  const filteredHistory = useMemo(() => {
    return (data?.inspection_history ?? []).filter((record) => {
      if (!historySearch) {
        return true
      }

      return `${record.establishment_name} ${record.business_type ?? ''} ${record.date}`
        .toLowerCase()
        .includes(historySearch.toLowerCase())
    })
  }, [data, historySearch])

  const sync = data?.sync ?? {}
  const isSynced = !sync.pending_count && !sync.unsynced_assignments
  const canStart = selected && (selected.status === 'assigned' || selected.status === 'downloaded')
  const canSubmit = selected?.status === 'in_progress'
  const checklistLoading = checklistQuery.isLoading
  const hasChecklistEntries = Object.values(results).some(Boolean)

  const applicableChecklists = useMemo(() => {
    const groups = (checklistQuery.data?.checklists ?? []).map((checklist) => ({
      ...checklist,
      applicable: selected ? String(checklist.category).toLowerCase() === String(selected.category).toLowerCase() : false,
    }))

    return groups.sort((a, b) => Number(b.applicable) - Number(a.applicable))
  }, [checklistQuery.data, selected])

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-2xl font-semibold tracking-tight">Inspector Dashboard</h2>
            {dashboardQuery.isFetching && !loading && (
              <span className="text-xs text-muted-foreground">Refreshing...</span>
            )}
          </div>
          <p className="text-sm text-muted-foreground">
            Welcome back, {user?.name?.split(' ')[0] ?? 'Inspector'} &middot; manage your assigned inspections
          </p>
        </div>
        <Badge
          variant={isSynced ? 'default' : 'secondary'}
          className="gap-1.5 rounded-md px-2.5 py-1"
        >
          <RefreshCw className="size-3" />
          {isSynced ? 'All synced' : `${(sync.pending_count ?? 0) + (sync.unsynced_assignments ?? 0)} pending sync`}
          {sync.last_synced_at && (
            <span className="ml-1 text-[0.65rem] opacity-75">
              &middot; {new Date(sync.last_synced_at).toLocaleString()}
            </span>
          )}
        </Badge>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        {statsList.map((stat) => (
          <StatCard
            key={stat.title}
            title={stat.title}
            value={stat.value}
            description={stat.description}
            icon={stat.icon}
            loading={loading}
            isError={isError}
          />
        ))}
      </div>

      <Tabs value={tab} onValueChange={setTab}>
        <TabsList>
          <TabsTrigger value="assigned">Assigned Inspections</TabsTrigger>
          <TabsTrigger value="history">Inspection History</TabsTrigger>
          <TabsTrigger value="followups">Follow-ups</TabsTrigger>
        </TabsList>

        <TabsContent value="assigned" className="space-y-4">
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="flex items-center gap-2 text-base">
                <ClipboardCheck className="size-4 text-muted-foreground" />
                Inspection Queue
              </CardTitle>
              <CardDescription>
                Inspections assigned to you, filterable by category, status, and date
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                  <Label className="text-xs text-muted-foreground">Status</Label>
                  <select
                    className="mt-1 h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                  >
                    {STATUS_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <Label className="text-xs text-muted-foreground">Category</Label>
                  <select
                    className="mt-1 h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                    value={categoryFilter}
                    onChange={(e) => setCategoryFilter(e.target.value)}
                  >
                    <option value="all">All Categories</option>
                    {categories.map((category) => (
                      <option key={category} value={category}>
                        {category}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <Label className="text-xs text-muted-foreground">Assigned Date</Label>
                  <Input
                    type="date"
                    className="mt-1 h-9"
                    value={dateFilter}
                    onChange={(e) => setDateFilter(e.target.value)}
                  />
                </div>
                <div>
                  <Label className="text-xs text-muted-foreground">Search</Label>
                  <Input
                    className="mt-1 h-9"
                    placeholder="Business, applicant, or request no."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                  />
                </div>
              </div>

              <div className="mt-4">
                {loading ? (
                  <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {[0, 1, 2].map((index) => (
                      <Skeleton key={index} className="h-28 rounded-xl" />
                    ))}
                  </div>
                ) : filteredAssignments.length === 0 ? (
                  <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    No assigned inspections match the current filters.
                  </div>
                ) : (
                  <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {filteredAssignments.map((assignment) => {
                      const isSelected = assignment.id === selectedId

                      return (
                        <button
                          key={assignment.id}
                          type="button"
                          onClick={() => setSelectedId(assignment.id)}
                          className={cn(
                            'group rounded-xl border bg-card p-4 text-left transition-colors',
                            isSelected
                              ? 'border-primary ring-1 ring-primary'
                              : 'border-border hover:border-primary/50',
                          )}
                        >
                          <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                              <p className="truncate text-sm font-semibold">
                                {assignment.business_name || assignment.applicant_name}
                              </p>
                              <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                {assignment.request_number}
                              </p>
                            </div>
                            <Badge variant={assignmentStatusVariant(assignment.status)}>
                              {titleCase(assignment.status)}
                            </Badge>
                          </div>
                          <div className="mt-3 flex flex-wrap items-center gap-1.5">
                            <Badge variant="outline">{assignment.category}</Badge>
                            <Badge variant="secondary">{assignment.application_type}</Badge>
                          </div>
                          <p className="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <User className="size-3" />
                            {assignment.applicant_name} &middot;
                            <CalendarDays className="size-3" />
                            {assignment.assigned_at}
                          </p>
                          {assignment.scheduled_at && (
                            <p className="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-primary">
                              <CalendarDays className="size-3" />
                              Scheduled: {assignment.scheduled_at}
                            </p>
                          )}
                        </button>
                      )
                    })}
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          {selected ? (
            <Card className="border-primary/40">
              <CardHeader className="pb-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <CardTitle className="flex flex-wrap items-center gap-2 text-lg">
                      <Building2 className="size-5 text-primary" />
                      {selected.business_name || selected.applicant_name}
                      <Badge variant={assignmentStatusVariant(selected.status)}>
                        {titleCase(selected.status)}
                      </Badge>
                    </CardTitle>
                    <CardDescription className="mt-1">
                      {selected.request_number} &middot; {selected.category} &middot;{' '}
                      {selected.application_type}
                    </CardDescription>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    {canStart && (
                      <Button
                        size="sm"
                        onClick={() => startMutation.mutate()}
                        disabled={startMutation.isPending}
                      >
                        {startMutation.isPending ? (
                          <Loader2 className="size-4 animate-spin" />
                        ) : (
                          <ClipboardCheck className="size-4" />
                        )}
                        Start Inspection
                      </Button>
                    )}
                    {canSubmit && (
                      <Button size="sm" onClick={() => setSubmitOpen(true)}>
                        <Send className="size-4" />
                        Submit Inspection
                      </Button>
                    )}
                    {selected.status === 'submitted' && (
                      <Badge className="gap-1">
                        <CheckCircle2 className="size-3" />
                        Submitted for review
                      </Badge>
                    )}
                    <Button size="sm" variant="outline" onClick={() => setSelectedId(null)}>
                      <ArrowLeft className="size-4" />
                      Close
                    </Button>
                  </div>
                </div>
              </CardHeader>

              <CardContent className="space-y-6">
                <div className="grid gap-6 lg:grid-cols-2">
                  <Card className="border-border/70">
                    <CardHeader className="pb-2">
                      <CardTitle className="flex items-center gap-2 text-sm">
                        <User className="size-4 text-muted-foreground" />
                        Applicant &amp; Establishment Details
                      </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2">
                      <DetailRow icon={User} label="Applicant" value={selected.applicant_name} />
                      <DetailRow icon={Users} label="Age" value={selected.applicant_age} />
                      <DetailRow icon={MapPin} label="Applicant Address" value={selected.applicant_address} />
                      <DetailRow icon={Phone} label="Contact" value={selected.contact_number} />
                      <DetailRow icon={MessageSquare} label="Email" value={selected.email} />
                      <DetailRow icon={Building2} label="Establishment" value={selected.establishment_name} />
                      <DetailRow icon={CalendarDays} label="Scheduled" value={selected.scheduled_at} />
                      <DetailRow icon={MapPin} label="Establishment Address" value={selected.establishment_address} />
                      <DetailRow icon={MapPin} label="Barangay" value={selected.barangay} />
                      <DetailRow icon={FileText} label="Business Type" value={selected.business_type} />
                      <DetailRow icon={ClipboardCheck} label="Application Type" value={selected.application_type} />
                    </CardContent>
                  </Card>

                  <Card className="border-border/70">
                    <CardHeader className="pb-2">
                      <CardTitle className="flex items-center gap-2 text-sm">
                        <AlertTriangle className="size-4 text-muted-foreground" />
                        Violations Found
                      </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                      {reportQuery.isLoading ? (
                        <div className="space-y-2">
                          <Skeleton className="h-10 w-full" />
                          <Skeleton className="h-10 w-full" />
                        </div>
                      ) : (reportQuery.data?.violations ?? []).length === 0 ? (
                        <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                          No violations recorded for this inspection.
                        </p>
                      ) : (
                        <div className="space-y-2">
                          {(reportQuery.data?.violations ?? []).map((violation) => (
                            <div
                              key={violation.id}
                              className="flex items-start justify-between gap-2 rounded-lg border border-border bg-muted/30 p-3"
                            >
                              <div className="min-w-0">
                                <p className="truncate text-sm font-medium">{violation.title}</p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                  {violation.description || 'No description'}
                                  {violation.correction_deadline
                                    ? ` &middot; deadline ${violation.correction_deadline}`
                                    : ''}
                                </p>
                              </div>
                              <div className="flex shrink-0 flex-col items-end gap-1">
                                <Badge variant={violationSeverityVariant(violation.severity)}>
                                  {titleCase(violation.severity)}
                                </Badge>
                                <Badge variant={violationStatusVariant(violation.status)}>
                                  {titleCase(violation.status)}
                                </Badge>
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                      <Button
                        size="sm"
                        variant="outline"
                        className="w-full"
                        onClick={() => setViolationOpen(true)}
                        disabled={!canSubmit && !canStart}
                      >
                        <AlertTriangle className="size-4" />
                        Record Violation
                      </Button>
                    </CardContent>
                  </Card>
                </div>

                <Card className="border-border/70">
                  <CardHeader className="pb-2">
                    <CardTitle className="flex items-center gap-2 text-sm">
                      <ClipboardCheck className="size-4 text-muted-foreground" />
                      Dynamic Compliance Checklist
                    </CardTitle>
                    <CardDescription>
                      Assess each item per applicable category and attach photo evidence
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {checklistLoading ? (
                      <div className="space-y-3">
                        <Skeleton className="h-24 w-full" />
                        <Skeleton className="h-24 w-full" />
                      </div>
                    ) : applicableChecklists.length === 0 ? (
                      <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No active compliance checklists are configured.
                      </p>
                    ) : (
                      applicableChecklists.map((checklist) => (
                        <div key={checklist.id} className="space-y-2">
                          <div className="flex flex-wrap items-center gap-2">
                            <h4 className="text-sm font-semibold">{checklist.category}</h4>
                            <Badge variant="secondary">{checklist.name}</Badge>
                            {checklist.applicable && (
                              <Badge className="bg-primary/10 text-primary hover:bg-primary/10">Applicable</Badge>
                            )}
                          </div>
                          <div className="space-y-2">
                            {(checklist.items ?? []).map((item) => (
                              <div
                                key={item.id}
                                className="flex flex-col gap-2 rounded-lg border border-border bg-muted/30 p-3 sm:flex-row sm:items-start sm:justify-between"
                              >
                                <div className="min-w-0 flex-1">
                                  <div className="flex items-center gap-2">
                                    <p className="text-sm font-medium">{item.title}</p>
                                    {item.is_required && <Badge variant="outline">Required</Badge>}
                                  </div>
                                  {item.description && (
                                    <p className="mt-0.5 text-xs text-muted-foreground">{item.description}</p>
                                  )}
                                </div>
                                <div className="flex flex-col gap-2 sm:w-80">
                                  <select
                                    className="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                                    value={results[item.id] ?? ''}
                                    onChange={(e) =>
                                      setResults((prev) => ({ ...prev, [item.id]: e.target.value }))
                                    }
                                    disabled={!canStart && !canSubmit}
                                  >
                                    <option value="">Select status</option>
                                    <option value="compliant">Compliant</option>
                                    <option value="non_compliant">Non-Compliant</option>
                                    <option value="needs_correction">Needs Correction</option>
                                  </select>
                                  <Input
                                    className="h-9"
                                    placeholder="Remarks (optional)"
                                    value={itemRemarks[item.id] ?? ''}
                                    onChange={(e) =>
                                      setItemRemarks((prev) => ({ ...prev, [item.id]: e.target.value }))
                                    }
                                    disabled={!canStart && !canSubmit}
                                  />
                                  <EvidenceDropzone
                                    files={evidence[item.id]}
                                    onChange={(files) => setEvidence((prev) => ({ ...prev, [item.id]: files }))}
                                    disabled={!canStart && !canSubmit}
                                  />
                                </div>
                              </div>
                            ))}
                          </div>
                        </div>
                      ))
                    )}
                    {(canStart || canSubmit) && (
                      <Button
                        onClick={() => saveChecklistMutation.mutate()}
                        disabled={!hasChecklistEntries || saveChecklistMutation.isPending}
                      >
                        {saveChecklistMutation.isPending ? (
                          <Loader2 className="size-4 animate-spin" />
                        ) : (
                          <Upload className="size-4" />
                        )}
                        Save Checklist &amp; Evidence
                      </Button>
                    )}
                  </CardContent>
                </Card>

                <Card className="border-border/70">
                  <CardHeader className="pb-2">
                    <CardTitle className="flex items-center gap-2 text-sm">
                      <FileText className="size-4 text-muted-foreground" />
                      Inspection Report &amp; Inspector Remarks
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <div>
                      <Label className="text-xs text-muted-foreground">Overall Assessment</Label>
                      <textarea
                        className="mt-1 min-h-24 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                        placeholder="Summarize the overall compliance of the establishment..."
                        value={assessment}
                        onChange={(e) => setAssessment(e.target.value)}
                        disabled={!canStart && !canSubmit}
                      />
                    </div>
                    <div>
                      <Label className="text-xs text-muted-foreground">Recommendations</Label>
                      <textarea
                        className="mt-1 min-h-20 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                        placeholder="Recommended corrective actions..."
                        value={recommendations}
                        onChange={(e) => setRecommendations(e.target.value)}
                        disabled={!canStart && !canSubmit}
                      />
                    </div>
                    <div>
                      <Label className="text-xs text-muted-foreground">Inspector Remarks</Label>
                      <textarea
                        className="mt-1 min-h-20 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                        placeholder="Notes for the barangay staff reviewing this submission..."
                        value={inspectorNotes}
                        onChange={(e) => setInspectorNotes(e.target.value)}
                        disabled={!canStart && !canSubmit}
                      />
                    </div>
                    {(canStart || canSubmit) && (
                      <Button
                        variant="outline"
                        onClick={() =>
                          saveReportMutation.mutate({
                            overall_assessment: assessment,
                            recommendations,
                            notes: inspectorNotes,
                          })
                        }
                        disabled={saveReportMutation.isPending}
                      >
                        {saveReportMutation.isPending ? (
                          <Loader2 className="size-4 animate-spin" />
                        ) : (
                          <FileText className="size-4" />
                        )}
                        Save Report &amp; Remarks
                      </Button>
                    )}
                  </CardContent>
                </Card>
              </CardContent>
            </Card>
          ) : (
            !loading && (
              <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                Select an inspection above to view its details, run the compliance checklist, and submit
                the inspection.
              </div>
            )
          )}
        </TabsContent>

        <TabsContent value="history" className="space-y-4">
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="flex items-center gap-2 text-base">
                <CheckCircle2 className="size-4 text-muted-foreground" />
                Inspection History
              </CardTitle>
              <CardDescription>Inspections you have completed</CardDescription>
            </CardHeader>
            <CardContent>
              <Input
                className="mb-3 max-w-sm"
                placeholder="Search by establishment or business type..."
                value={historySearch}
                onChange={(e) => setHistorySearch(e.target.value)}
              />
              {loading ? (
                <div className="space-y-2">
                  {[0, 1, 2].map((index) => (
                    <Skeleton key={index} className="h-14 w-full" />
                  ))}
                </div>
              ) : filteredHistory.length === 0 ? (
                <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                  No completed inspections found.
                </p>
              ) : (
                <div className="divide-y divide-border rounded-lg border border-border">
                  {filteredHistory.map((record) => (
                    <div key={record.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium">{record.establishment_name}</p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                          {record.business_type || 'Establishment'} &middot; {record.date}
                        </p>
                        {record.overall_assessment && (
                          <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                            {record.overall_assessment}
                          </p>
                        )}
                      </div>
                      <Badge variant="default">Completed</Badge>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="followups" className="space-y-4">
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="flex items-center gap-2 text-base">
                <RefreshCw className="size-4 text-muted-foreground" />
                Follow-up Inspections
              </CardTitle>
              <CardDescription>Cases requiring follow-up inspection or corrective action</CardDescription>
            </CardHeader>
            <CardContent>
              {loading ? (
                <div className="space-y-2">
                  {[0, 1, 2].map((index) => (
                    <Skeleton key={index} className="h-16 w-full" />
                  ))}
                </div>
              ) : (data?.follow_ups ?? []).length === 0 ? (
                <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                  No follow-up inspections assigned to you.
                </p>
              ) : (
                <div className="divide-y divide-border rounded-lg border border-border">
                  {(data?.follow_ups ?? []).map((followUp) => (
                    <div
                      key={followUp.id}
                      className="flex flex-wrap items-center justify-between gap-2 p-3"
                    >
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                          {followUp.business_name || followUp.request_number}
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                          {followUp.request_number} &middot; {followUp.category} &middot; updated{' '}
                          {followUp.updated_at}
                        </p>
                        {followUp.reason && (
                          <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                            Reason: {followUp.reason}
                          </p>
                        )}
                      </div>
                      <div className="flex shrink-0 items-center gap-2">
                        <Badge variant="secondary">{titleCase(followUp.status)}</Badge>
                        <Button
                          size="sm"
                          variant="outline"
                          disabled={!followUp.assignment_id}
                          onClick={() => {
                            setSelectedId(followUp.assignment_id)
                            setTab('assigned')
                          }}
                        >
                          Conduct Follow-up
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={violationOpen} onOpenChange={setViolationOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Record Violation</DialogTitle>
            <DialogDescription>
              Document a violation found during this inspection of{' '}
              {selected?.business_name || selected?.establishment_name || 'the establishment'}.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label className="text-xs text-muted-foreground">Title</Label>
              <Input
                className="mt-1"
                placeholder="e.g. Improper food storage"
                value={violationForm.title}
                onChange={(e) => setViolationForm((prev) => ({ ...prev, title: e.target.value }))}
              />
            </div>
            <div>
              <Label className="text-xs text-muted-foreground">Description</Label>
              <textarea
                className="mt-1 min-h-20 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                placeholder="Describe the violation and required corrective actions..."
                value={violationForm.description}
                onChange={(e) =>
                  setViolationForm((prev) => ({ ...prev, description: e.target.value }))
                }
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-xs text-muted-foreground">Severity</Label>
                <select
                  className="mt-1 h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                  value={violationForm.severity}
                  onChange={(e) =>
                    setViolationForm((prev) => ({ ...prev, severity: e.target.value }))
                  }
                >
                  <option value="minor">Minor</option>
                  <option value="moderate">Moderate</option>
                  <option value="major">Major</option>
                </select>
              </div>
              <div>
                <Label className="text-xs text-muted-foreground">Status</Label>
                <select
                  className="mt-1 h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                  value={violationForm.status}
                  onChange={(e) => setViolationForm((prev) => ({ ...prev, status: e.target.value }))}
                >
                  <option value="open">Open</option>
                  <option value="under_review">Under Review</option>
                </select>
              </div>
            </div>
            <div>
              <Label className="text-xs text-muted-foreground">Correction Deadline</Label>
              <Input
                type="date"
                className="mt-1"
                value={violationForm.correction_deadline}
                onChange={(e) =>
                  setViolationForm((prev) => ({
                    ...prev,
                    correction_deadline: e.target.value,
                  }))
                }
              />
            </div>
            <div>
              <Label className="text-xs text-muted-foreground">Photo Evidence</Label>
              <div className="mt-1">
                <EvidenceDropzone files={violationFiles} onChange={setViolationFiles} />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setViolationOpen(false)}>
              Cancel
            </Button>
            <Button
              onClick={() =>
                createViolationMutation.mutate({
                  inspection_id: reportQuery.data?.id ?? checklistQuery.data?.inspection?.id,
                  title: violationForm.title,
                  description: violationForm.description,
                  severity: violationForm.severity,
                  status: violationForm.status,
                  correction_deadline: violationForm.correction_deadline || undefined,
                })
              }
              disabled={
                !violationForm.title ||
                !reportQuery.data?.id ||
                createViolationMutation.isPending
              }
            >
              {createViolationMutation.isPending ? (
                <Loader2 className="size-4 animate-spin" />
              ) : (
                <AlertTriangle className="size-4" />
              )}
              Record Violation
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={submitOpen} onOpenChange={setSubmitOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Submit Inspection</DialogTitle>
            <DialogDescription>
              Submit {selected?.business_name || selected?.establishment_name || 'this inspection'} for
              review by barangay staff. This will notify the applicant that the inspection is complete.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setSubmitOpen(false)}>
              Cancel
            </Button>
            <Button onClick={() => submitMutation.mutate()} disabled={submitMutation.isPending}>
              {submitMutation.isPending ? (
                <Loader2 className="size-4 animate-spin" />
              ) : (
                <Send className="size-4" />
              )}
              Confirm Submission
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
