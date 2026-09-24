import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import {
  Activity,
  AlertTriangle,
  ArrowRight,
  BarChart3,
  Building2,
  CalendarDays,
  ClipboardCheck,
  Clock,
  Database,
  FileStack,
  FileText,
  History,
  Hourglass,
  Inbox,
  Layers,
  ShieldCheck,
  UserCheck,
  Users,
} from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Pie,
  PieChart,
  XAxis,
  YAxis,
} from 'recharts'

import api from '@/services/api'
import { Badge } from '@/components/ui/badge'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart'
import { Skeleton } from '@/components/ui/skeleton'

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const inspectionChartConfig = {
  total: { label: 'Total', color: 'var(--color-chart-1)' },
  completed: { label: 'Completed', color: 'var(--color-chart-2)' },
}

const clearanceChartConfig = {
  clearances: { label: 'Clearances', color: 'var(--color-chart-3)' },
}

const violationSeverityConfig = {
  minor: { label: 'Minor', color: 'var(--color-chart-1)' },
  moderate: { label: 'Moderate', color: 'var(--color-chart-2)' },
  major: { label: 'Major', color: 'var(--color-chart-4)' },
}

const violationStatusConfig = {
  open: { label: 'Open', color: 'var(--color-chart-2)' },
  resolved: { label: 'Resolved', color: 'var(--color-chart-1)' },
}

const systemTotalsChartConfig = {
  total: { label: 'Total', color: 'var(--color-chart-1)' },
}

const SEVERITY_COLORS = ['var(--color-chart-1)', 'var(--color-chart-2)', 'var(--color-chart-4)']
const STATUS_COLORS = ['var(--color-chart-2)', 'var(--color-chart-1)']
const CATEGORY_COLORS = [
  'var(--color-chart-1)',
  'var(--color-chart-2)',
  'var(--color-chart-3)',
  'var(--color-chart-4)',
  'var(--color-chart-5)',
]


function requestStatusVariant() {
  return 'secondary'
}

function assignmentStatusVariant(status) {
  const value = String(status).toLowerCase()

  if (value === 'in progress' || value === 'in_progress') {
    return 'secondary'
  }

  if (value === 'downloaded') {
    return 'default'
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
  if (status === 'under_review') {
    return 'secondary'
  }

  return 'destructive'
}

function clearanceVariant(daysLeft) {
  if (daysLeft === null || daysLeft === undefined) {
    return 'outline'
  }

  if (daysLeft <= 7) {
    return 'destructive'
  }

  if (daysLeft <= 14) {
    return 'secondary'
  }

  return 'outline'
}

function roleVariant(slug) {
  const value = String(slug).toLowerCase()
  if (value === 'administrator') return 'destructive'
  if (value === 'barangay_staff') return 'default'
  if (value === 'inspector') return 'secondary'
  return 'outline'
}

function formatRelativeTime(isoValue) {
  if (!isoValue) {
    return 'Recently'
  }

  const then = new Date(isoValue)
  const seconds = Math.round((Date.now() - then.getTime()) / 1000)

  if (Number.isNaN(seconds) || seconds < 0) {
    return 'Recently'
  }

  const intervals = [
    { label: 'year', seconds: 31536000 },
    { label: 'month', seconds: 2592000 },
    { label: 'day', seconds: 86400 },
    { label: 'hour', seconds: 3600 },
    { label: 'minute', seconds: 60 },
  ]

  for (const interval of intervals) {
    const count = Math.floor(seconds / interval.seconds)

    if (count >= 1) {
      return `${count} ${interval.label}${count > 1 ? 's' : ''} ago`
    }
  }

  return 'just now'
}

function titleCase(value) {
  if (!value) {
    return '—'
  }

  return String(value).replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

function buildMonthlySeries(rows) {
  const list = Array.isArray(rows)
    ? rows
    : rows && typeof rows === 'object'
      ? Object.values(rows)
      : []

  return MONTH_LABELS.map((label, index) => {
    const row = list.find((item) => Number(item.month) === index + 1) ?? {}

    return {
      month: label,
      total: row.total ?? 0,
      completed: row.completed ?? 0,
      clearances: row.clearances ?? 0,
    }
  })
}

function hasAnyData(series) {
  return (series ?? []).some(
    (row) => row.total > 0 || row.completed > 0 || row.clearances > 0,
  )
}

export default function DashboardPage() {
  const {
    data,
    error,
    isError,
    isFetching,
    isLoading,
  } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => {
      const response = await api.get('/v1/dashboard')
      return response.data.data
    },
  })

  useEffect(() => {
    if (isError) {
      toast.error('Failed to load dashboard metrics')
      console.error(error)
    }
  }, [error, isError])

  const loading = isLoading && !data

  const statsList = [
    {
      title: 'Pending Requests',
      value: data?.stats?.pending_requests,
      description: 'Awaiting review',
      icon: Inbox,
    },
    {
      title: 'Assigned Inspectors',
      value: data?.stats?.assigned_inspectors,
      description: 'With active inspections',
      icon: UserCheck,
    },
    {
      title: 'Completed Inspections',
      value: data?.stats?.completed_inspections,
      description: 'Year to date',
      icon: ClipboardCheck,
    },
    {
      title: 'Active Violations',
      value: data?.stats?.active_violations,
      description: 'Requiring follow-up',
      icon: AlertTriangle,
    },
    {
      title: 'Expiring Clearances',
      value: data?.stats?.expiring_clearances,
      description: 'Within the next 30 days',
      icon: Clock,
    },
    {
      title: 'Active Establishments',
      value: data?.stats?.active_establishments,
      description: 'Registered in the system',
      icon: Building2,
    },
  ]

  const pendingRequests = Array.isArray(data?.pending_requests) ? data.pending_requests : []
  const assignedInspectors = Array.isArray(data?.assigned_inspectors) ? data.assigned_inspectors : []
  const completedInspections = Array.isArray(data?.completed_inspections) ? data.completed_inspections : []
  const activeViolations = Array.isArray(data?.active_violations) ? data.active_violations : []
  const expiringClearances = Array.isArray(data?.expiring_clearances) ? data.expiring_clearances : []
  const recentActivities = Array.isArray(data?.recent_activities) ? data.recent_activities : []
  const systemTotals = data?.system_totals ?? {}
  const recentUsers = Array.isArray(data?.recent_users) ? data.recent_users : []
  const requestsByCategory = Array.isArray(data?.requests_by_category) ? data.requests_by_category : []
  const establishmentsByCategory = Array.isArray(data?.establishments_by_category)
    ? data.establishments_by_category
    : []

  const chartYear = data?.chart?.year ?? new Date().getFullYear()
  const monthlySeries = buildMonthlySeries(data?.chart?.monthly ?? [])

  const violationStats = data?.chart?.violations ?? {}
  const severityData = [
    { key: 'minor', label: 'Minor', value: violationStats.by_severity?.minor ?? 0 },
    { key: 'moderate', label: 'Moderate', value: violationStats.by_severity?.moderate ?? 0 },
    { key: 'major', label: 'Major', value: violationStats.by_severity?.major ?? 0 },
  ]
  const statusData = [
    { key: 'open', label: 'Open', value: violationStats.by_status?.open ?? 0 },
    { key: 'resolved', label: 'Resolved', value: violationStats.by_status?.resolved ?? 0 },
  ]
  const hasViolations = severityData.some((item) => item.value > 0)

  const systemTotalsList = [
    {
      title: 'Total Users',
      value: systemTotals.total_users,
      description: 'Registered accounts',
      icon: Users,
    },
    {
      title: 'Total Establishments',
      value: systemTotals.total_establishments,
      description: 'All time',
      icon: Building2,
    },
    {
      title: 'Total Requests',
      value: systemTotals.total_requests,
      description: 'Inspection requests',
      icon: FileStack,
    },
    {
      title: 'Total Inspections',
      value: systemTotals.total_inspections,
      description: 'All time',
      icon: ClipboardCheck,
    },
    {
      title: 'Total Violations',
      value: systemTotals.total_violations,
      description: 'Recorded',
      icon: ShieldCheck,
    },
    {
      title: 'Total Clearances',
      value: systemTotals.total_clearances,
      description: 'Issued',
      icon: Layers,
    },
  ]

  const hasRequestsByCategory = requestsByCategory.some((r) => r.total > 0)
  const hasEstablishmentsByCategory = establishmentsByCategory.some((r) => r.total > 0)

  return (
    <div className="space-y-6">
      <div>
        <div className="flex items-center gap-2">
          <h2 className="text-2xl font-semibold tracking-tight">Dashboard</h2>
          {isFetching && !loading && (
            <span className="text-xs text-muted-foreground">Refreshing...</span>
          )}
        </div>
        <p className="text-sm text-muted-foreground">
          Health and Safety Inspection System · Barangay 178, North Caloocan City · Barangay-Level Administrative System
        </p>
        <p className="text-xs text-muted-foreground/80">
          Overview of health and safety inspection activities — scoped to Barangay 178 only
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        {statsList.map((stat) => {
          const Icon = stat.icon

          return (
            <Card key={stat.title}>
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
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
                    <div className="text-3xl font-bold">
                      {isError ? '--' : (stat.value ?? 0)}
                    </div>
                    <p className="text-xs text-muted-foreground">{stat.description}</p>
                  </>
                )}
              </CardContent>
            </Card>
          )
        })}
      </div>

      <div>
        <div className="flex items-center gap-2">
          <Database className="size-4 text-muted-foreground" />
          <h3 className="text-lg font-semibold tracking-tight">System Overview</h3>
          <span className="text-xs text-muted-foreground">All-time totals</span>
        </div>
        <p className="text-sm text-muted-foreground">Lifetime counts across all modules</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        {systemTotalsList.map((stat) => {
          const Icon = stat.icon
          return (
            <Card key={stat.title} className="border-dashed">
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
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
                    <div className="text-3xl font-bold">{isError ? '--' : (stat.value ?? 0)}</div>
                    <p className="text-xs text-muted-foreground">{stat.description}</p>
                  </>
                )}
              </CardContent>
            </Card>
          )
        })}
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Hourglass className="size-4 text-muted-foreground" />
              Pending Inspection Requests
            </CardTitle>
            <CardDescription>
              New submissions awaiting staff review
            </CardDescription>
          </CardHeader>
          <CardContent>
            <WidgetBody
              loading={loading}
              isError={isError}
              isEmpty={pendingRequests.length === 0}
              emptyText="No pending inspection requests."
            >
              <div className="divide-y divide-border">
                {pendingRequests.map((request) => (
                  <Link
                    key={request.id}
                    to="/inspection-requests"
                    className="flex items-start gap-3 py-3 first:pt-0 last:pb-0 hover:opacity-80"
                  >
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                      <FileText className="size-4 text-muted-foreground" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <p className="truncate text-sm font-medium">
                          {request.business_name || request.applicant_name}
                        </p>
                        <Badge variant={requestStatusVariant()}>{titleCase(request.category)}</Badge>
                      </div>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {request.request_number} &middot; submitted {request.submitted_at}
                      </p>
                    </div>
                    <ArrowRight className="mt-1 size-4 shrink-0 text-muted-foreground" />
                  </Link>
                ))}
              </div>
            </WidgetBody>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <UserCheck className="size-4 text-muted-foreground" />
              Assigned Inspectors
            </CardTitle>
            <CardDescription>
              Active inspection assignments
            </CardDescription>
          </CardHeader>
          <CardContent>
            <WidgetBody
              loading={loading}
              isError={isError}
              isEmpty={assignedInspectors.length === 0}
              emptyText="No inspectors currently assigned."
            >
              <div className="divide-y divide-border">
                {assignedInspectors.map((assignment) => (
                  <div key={assignment.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent/10 text-xs font-bold text-accent ring-1 ring-accent/20">
                      {String(assignment.inspector_name).split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase()}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <p className="truncate text-sm font-medium">{assignment.inspector_name}</p>
                        <Badge variant={assignmentStatusVariant(assignment.status)}>
                          {assignment.status}
                        </Badge>
                      </div>
                      <p className="mt-0.5 truncate text-xs text-muted-foreground">
                        {assignment.business_name || assignment.request_number}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </WidgetBody>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Clock className="size-4 text-muted-foreground" />
              Expiring Clearances
            </CardTitle>
            <CardDescription>
              Active clearances expiring within 30 days
            </CardDescription>
          </CardHeader>
          <CardContent>
            <WidgetBody
              loading={loading}
              isError={isError}
              isEmpty={expiringClearances.length === 0}
              emptyText="No clearances expiring soon."
            >
              <div className="divide-y divide-border">
                {expiringClearances.map((clearance) => (
                  <div key={clearance.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                      <CalendarDays className="size-4 text-muted-foreground" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <p className="truncate text-sm font-medium">{clearance.establishment_name}</p>
                        <Badge variant={clearanceVariant(clearance.days_left)}>
                          {clearance.days_left === null
                            ? 'Expiring'
                            : `${clearance.days_left} day${clearance.days_left === 1 ? '' : 's'} left`}
                        </Badge>
                      </div>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {clearance.clearance_type} &middot; expires {clearance.expiration_date}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </WidgetBody>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <AlertTriangle className="size-4 text-muted-foreground" />
              Active Violations
            </CardTitle>
            <CardDescription>
              Open violations requiring corrective action
            </CardDescription>
          </CardHeader>
          <CardContent>
            <WidgetBody
              loading={loading}
              isError={isError}
              isEmpty={activeViolations.length === 0}
              emptyText="No active violations."
            >
              <div className="divide-y divide-border">
                {activeViolations.map((violation) => (
                  <div key={violation.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-destructive/10">
                      <AlertTriangle className="size-4 text-destructive" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <p className="truncate text-sm font-medium">{violation.title}</p>
                        <Badge variant={violationSeverityVariant(violation.severity)}>
                          {titleCase(violation.severity)}
                        </Badge>
                      </div>
                      <p className="mt-0.5 truncate text-xs text-muted-foreground">
                        {violation.establishment_name}
                        {violation.correction_deadline
                          ? ` &middot; deadline ${violation.correction_deadline}`
                          : ''}
                      </p>
                    </div>
                    <Badge variant={violationStatusVariant(violation.status)}>
                      {titleCase(violation.status)}
                    </Badge>
                  </div>
                ))}
              </div>
            </WidgetBody>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <ClipboardCheck className="size-4 text-muted-foreground" />
              Completed Inspections
            </CardTitle>
            <CardDescription>
              Most recently completed inspections
            </CardDescription>
          </CardHeader>
          <CardContent>
            <WidgetBody
              loading={loading}
              isError={isError}
              isEmpty={completedInspections.length === 0}
              emptyText="No completed inspections recorded."
            >
              <div className="divide-y divide-border">
                {completedInspections.map((inspection) => (
                  <div key={inspection.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-accent/10">
                      <ClipboardCheck className="size-4 text-accent" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">
                        {inspection.establishment_name}
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {inspection.inspector_name} &middot; {inspection.date}
                      </p>
                    </div>
                    <Badge variant="default">Completed</Badge>
                  </div>
                ))}
              </div>
            </WidgetBody>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Activity className="size-4 text-muted-foreground" />
              Recent Activities
            </CardTitle>
            <CardDescription>
              Latest system events and actions
            </CardDescription>
          </CardHeader>
          <CardContent>
            <WidgetBody
              loading={loading}
              isError={isError}
              isEmpty={recentActivities.length === 0}
              emptyText="No recent activities recorded."
            >
              <div className="divide-y divide-border">
                {recentActivities.map((activity) => (
                  <div key={activity.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                      <History className="size-4 text-muted-foreground" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">
                        {activity.description || titleCase(activity.action || activity.event)}
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {activity.user_name} &middot; {formatRelativeTime(activity.created_at)}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </WidgetBody>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Users className="size-4 text-muted-foreground" />
              Recent Registrations
            </CardTitle>
            <CardDescription>Newest accounts created</CardDescription>
          </CardHeader>
          <CardContent>
            <WidgetBody
              loading={loading}
              isError={isError}
              isEmpty={recentUsers.length === 0}
              emptyText="No registered users yet."
            >
              <div className="divide-y divide-border">
                {recentUsers.map((u) => (
                  <div key={u.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary ring-1 ring-primary/20">
                      {String(u.name).split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase()}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <p className="truncate text-sm font-medium">{u.name}</p>
                        <Badge variant={roleVariant(u.role_slug)}>{titleCase(u.role)}</Badge>
                        {!u.is_active && <Badge variant="outline">Inactive</Badge>}
                      </div>
                      <p className="mt-0.5 truncate text-xs text-muted-foreground">
                        {u.email} &middot; {formatRelativeTime(u.created_at)}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </WidgetBody>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Layers className="size-4 text-muted-foreground" />
              Requests by Category
            </CardTitle>
            <CardDescription>Inspection requests per category</CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <Skeleton className="h-64 w-full" />
            ) : isError ? (
              <p className="py-8 text-center text-sm text-muted-foreground">Chart data is unavailable.</p>
            ) : hasRequestsByCategory ? (
              <ChartContainer config={systemTotalsChartConfig} className="h-64 w-full">
                <BarChart data={requestsByCategory} layout="vertical" margin={{ left: 8 }}>
                  <CartesianGrid horizontal={false} />
                  <XAxis type="number" tickLine={false} axisLine={false} allowDecimals={false} />
                  <YAxis
                    type="category"
                    dataKey="category"
                    tickLine={false}
                    axisLine={false}
                    width={110}
                    tick={{ fontSize: 12 }}
                  />
                  <ChartTooltip cursor={false} content={<ChartTooltipContent hideLabel />} />
                  <Bar dataKey="total" radius={4}>
                    {requestsByCategory.map((entry, i) => (
                      <Cell key={entry.slug} fill={CATEGORY_COLORS[i % CATEGORY_COLORS.length]} />
                    ))}
                  </Bar>
                </BarChart>
              </ChartContainer>
            ) : (
              <p className="py-8 text-center text-sm text-muted-foreground">No requests recorded yet.</p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Building2 className="size-4 text-muted-foreground" />
              Establishments by Category
            </CardTitle>
            <CardDescription>Registered establishments per category</CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <Skeleton className="h-64 w-full" />
            ) : isError ? (
              <p className="py-8 text-center text-sm text-muted-foreground">Chart data is unavailable.</p>
            ) : hasEstablishmentsByCategory ? (
              <ChartContainer config={systemTotalsChartConfig} className="h-64 w-full">
                <BarChart data={establishmentsByCategory} layout="vertical" margin={{ left: 8 }}>
                  <CartesianGrid horizontal={false} />
                  <XAxis type="number" tickLine={false} axisLine={false} allowDecimals={false} />
                  <YAxis
                    type="category"
                    dataKey="category"
                    tickLine={false}
                    axisLine={false}
                    width={110}
                    tick={{ fontSize: 12 }}
                  />
                  <ChartTooltip cursor={false} content={<ChartTooltipContent hideLabel />} />
                  <Bar dataKey="total" radius={4}>
                    {establishmentsByCategory.map((entry, i) => (
                      <Cell key={entry.category} fill={CATEGORY_COLORS[i % CATEGORY_COLORS.length]} />
                    ))}
                  </Bar>
                </BarChart>
              </ChartContainer>
            ) : (
              <p className="py-8 text-center text-sm text-muted-foreground">No establishments recorded yet.</p>
            )}
          </CardContent>
        </Card>
      </div>

      <div>
        <div className="flex items-center gap-2">
          <BarChart3 className="size-4 text-muted-foreground" />
          <h3 className="text-lg font-semibold tracking-tight">Reports Overview</h3>
        </div>
        <p className="text-sm text-muted-foreground">
          Monthly trends and violation analytics for {chartYear}
        </p>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Monthly Inspection Activity</CardTitle>
            <CardDescription>
              Total and completed inspections per month
            </CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <Skeleton className="h-72 w-full" />
            ) : isError ? (
              <p className="py-8 text-center text-sm text-muted-foreground">
                Chart data is unavailable. Please refresh or try again later.
              </p>
            ) : hasAnyData(monthlySeries) ? (
              <ChartContainer config={inspectionChartConfig} className="h-72 w-full">
                <BarChart data={monthlySeries}>
                  <CartesianGrid vertical={false} />
                  <XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} />
                  <YAxis tickLine={false} axisLine={false} width={36} allowDecimals={false} />
                  <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="line" />} />
                  <ChartLegend content={<ChartLegendContent />} />
                  <Bar dataKey="total" fill="var(--color-total)" radius={4} />
                  <Bar dataKey="completed" fill="var(--color-completed)" radius={4} />
                </BarChart>
              </ChartContainer>
            ) : (
              <p className="py-8 text-center text-sm text-muted-foreground">
                No inspection data recorded yet this year.
              </p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Violations by Severity</CardTitle>
            <CardDescription>
              Distribution by severity level
            </CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <Skeleton className="h-64 w-full" />
            ) : isError ? (
              <p className="py-8 text-center text-sm text-muted-foreground">
                Chart data is unavailable.
              </p>
            ) : hasViolations ? (
              <ChartContainer config={violationSeverityConfig} className="mx-auto aspect-square h-64">
                <PieChart>
                  <ChartTooltip content={<ChartTooltipContent hideLabel />} />
                  <Pie data={severityData} dataKey="value" nameKey="label" innerRadius={60} strokeWidth={2}>
                    {severityData.map((entry, i) => (
                      <Cell key={entry.key} fill={SEVERITY_COLORS[i % SEVERITY_COLORS.length]} />
                    ))}
                  </Pie>
                  <ChartLegend content={<ChartLegendContent nameKey="label" />} />
                </PieChart>
              </ChartContainer>
            ) : (
              <p className="py-8 text-center text-sm text-muted-foreground">
                No violations recorded yet this year.
              </p>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Monthly Clearance Issuance</CardTitle>
            <CardDescription>
              Clearances issued per month
            </CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <Skeleton className="h-64 w-full" />
            ) : isError ? (
              <p className="py-8 text-center text-sm text-muted-foreground">
                Chart data is unavailable. Please refresh or try again later.
              </p>
            ) : hasAnyData(monthlySeries) ? (
              <ChartContainer config={clearanceChartConfig} className="h-64 w-full">
                <BarChart data={monthlySeries}>
                  <CartesianGrid vertical={false} />
                  <XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} />
                  <YAxis tickLine={false} axisLine={false} width={36} allowDecimals={false} />
                  <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="line" />} />
                  <ChartLegend content={<ChartLegendContent />} />
                  <Bar dataKey="clearances" fill="var(--color-clearances)" radius={4} />
                </BarChart>
              </ChartContainer>
            ) : (
              <p className="py-8 text-center text-sm text-muted-foreground">
                No clearances issued yet this year.
              </p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Violations by Status</CardTitle>
            <CardDescription>
              Open vs resolved violations
            </CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <Skeleton className="h-64 w-full" />
            ) : isError ? (
              <p className="py-8 text-center text-sm text-muted-foreground">
                Chart data is unavailable.
              </p>
            ) : hasViolations ? (
              <ChartContainer config={violationStatusConfig} className="h-64 w-full">
                <BarChart data={statusData}>
                  <CartesianGrid vertical={false} />
                  <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} />
                  <YAxis tickLine={false} axisLine={false} width={36} allowDecimals={false} />
                  <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="line" />} />
                  <Bar dataKey="value" radius={4}>
                    {statusData.map((entry, i) => (
                      <Cell key={entry.key} fill={STATUS_COLORS[i % STATUS_COLORS.length]} />
                    ))}
                  </Bar>
                </BarChart>
              </ChartContainer>
            ) : (
              <p className="py-8 text-center text-sm text-muted-foreground">
                No violations recorded yet this year.
              </p>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

function WidgetBody({ loading, isError, isEmpty, emptyText, children }) {
  if (isError) {
    return (
      <p className="py-6 text-center text-sm text-muted-foreground">
        Dashboard data is unavailable. Please refresh or try again later.
      </p>
    )
  }

  if (loading) {
    return (
      <div className="space-y-3 py-1">
        {[0, 1, 2].map((index) => (
          <Skeleton key={index} className="h-14 w-full" />
        ))}
      </div>
    )
  }

  if (isEmpty) {
    return <p className="py-6 text-center text-sm text-muted-foreground">{emptyText}</p>
  }

  return children
}
