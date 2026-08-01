import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  ArrowRight,
  Bell,
  Check,
  ClipboardCheck,
  FileText,
  History,
  Hourglass,
  Inbox,
  RefreshCw,
  ScrollText,
  ShieldCheck,
  Store,
  UserCheck,
} from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'

import api from '@/services/api'
import QrCode from '@/components/QrCode'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'

const statusLabels = {
  submitted: 'Submitted',
  under_review: 'Under Review',
  requirements_incomplete: 'Requirements Incomplete',
  approved_for_inspection: 'Approved',
  assigned: 'Inspector Assigned',
  violation_notice_issued: 'Violation Notice',
  follow_up_requested: 'Follow-up Requested',
  inspection_completed: 'Inspection Completed',
  clearance_approved: 'Clearance Issued',
}

function statusVariant(status) {
  if (status === 'requirements_incomplete' || status === 'violation_notice_issued') {
    return 'destructive'
  }

  if (status === 'submitted' || status === 'follow_up_requested') {
    return 'outline'
  }

  if (status === 'under_review' || status === 'assigned' || status === 'approved_for_inspection') {
    return 'secondary'
  }

  return 'default'
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

const MAIN_FLOW = [
  { key: 'submitted', label: 'Submitted' },
  { key: 'under_review', label: 'Under Review' },
  { key: 'approved_for_inspection', label: 'Approved for Inspection' },
  { key: 'assigned', label: 'Inspector Assigned' },
  { key: 'inspection_completed', label: 'Inspection Completed' },
  { key: 'clearance_approved', label: 'Clearance Issued' },
]

function markSteps(steps, currentIndex) {
  return steps.map((step, i) => ({
    ...step,
    done: i < currentIndex,
    current: i === currentIndex,
  }))
}

function buildFlow(status) {
  if (status === 'requirements_incomplete') {
    return markSteps(
      [
        { key: 'submitted', label: 'Submitted' },
        { key: 'under_review', label: 'Under Review' },
        { key: 'requirements_incomplete', label: 'Requirements Incomplete' },
      ],
      2,
    )
  }

  if (status === 'violation_notice_issued' || status === 'follow_up_requested') {
    const base = markSteps(MAIN_FLOW.slice(0, 5), 5)
    const isFollowUp = status === 'follow_up_requested'

    return [
      ...base,
      { key: 'violation_notice_issued', label: 'Violation Found', done: isFollowUp, current: !isFollowUp },
      { key: 'follow_up_requested', label: 'Follow-up Requested', done: false, current: isFollowUp },
    ]
  }

  const index = MAIN_FLOW.findIndex((step) => step.key === status)

  if (index === -1) {
    return []
  }

  return markSteps(MAIN_FLOW.slice(0, index + 1), index)
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

export default function ResidentDashboardPage() {
  const [selectedId, setSelectedId] = useState(null)

  const { data, error, isError, isFetching, isLoading } = useQuery({
    queryKey: ['resident-dashboard'],
    queryFn: async () => {
      const response = await api.get('/v1/dashboard')
      return response.data.data
    },
  })

  useEffect(() => {
    if (isError) {
      toast.error('Failed to load dashboard')
      console.error(error)
    }
  }, [error, isError])

  const loading = isLoading && !data

  const statsList = [
    { title: 'Total Applications', value: data?.stats?.total_requests ?? 0, description: 'Inspection requests submitted', icon: Inbox },
    { title: 'Pending Review', value: data?.stats?.pending ?? 0, description: 'Awaiting staff review', icon: Hourglass },
    { title: 'In Progress', value: data?.stats?.in_progress ?? 0, description: 'Inspections underway', icon: RefreshCw },
    { title: 'Completed', value: data?.stats?.completed ?? 0, description: 'Inspections done', icon: ClipboardCheck },
  ]

  const activeApplications = data?.active_applications ?? []
  const inspectionHistory = data?.inspection_history ?? []
  const activeClearances = data?.active_clearances ?? []
  const notifications = data?.notifications ?? []
  const unreadCount = data?.unread_count ?? 0

  const selectedApplication =
    activeApplications.find((application) => application.id === selectedId) ?? activeApplications[0] ?? null

  const selectedFlow = selectedApplication ? buildFlow(selectedApplication.status) : []

  return (
    <div className="space-y-6">
      <div>
        <div className="flex items-center gap-2">
          <h2 className="text-2xl font-semibold tracking-tight">Resident Dashboard</h2>
          {isFetching && !loading && <span className="text-xs text-muted-foreground">Refreshing...</span>}
        </div>
        <p className="text-sm text-muted-foreground">Track your applications, clearances, and inspection updates</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
              <ShieldCheck className="size-4 text-muted-foreground" />
              Active Clearance
            </CardTitle>
            <CardDescription>Your current Health &amp; Safety Clearances</CardDescription>
          </CardHeader>
          <CardContent>
            <WidgetBody
              loading={loading}
              isError={isError}
              isEmpty={activeClearances.length === 0}
              emptyText="No active clearances. Apply for inspection to obtain one."
            >
              <div className="space-y-4">
                {activeClearances.map((clearance) => (
                  <div key={clearance.id} className="space-y-4 rounded-lg border border-border p-4">
                    <div className="flex items-start gap-3">
                      <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-accent/10">
                        <Store className="size-4 text-accent" />
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">{clearance.establishment_name}</p>
                        <p className="mt-0.5 text-xs text-muted-foreground">{clearance.type}</p>
                      </div>
                      <Badge variant={clearanceVariant(clearance.days_left)}>
                        {clearance.days_left === null
                          ? 'Active'
                          : `${clearance.days_left} day${clearance.days_left === 1 ? '' : 's'} left`}
                      </Badge>
                    </div>
                    <div className="flex items-center gap-4">
                      {clearance.qr_code ? (
                        <div className="rounded-md bg-muted p-1.5">
                          <QrCode value={clearance.qr_code} size={88} />
                        </div>
                      ) : (
                        <div className="flex size-[100px] items-center justify-center rounded-md bg-muted text-xs text-muted-foreground">
                          No QR
                        </div>
                      )}
                      <div className="min-w-0 flex-1 space-y-2 text-sm">
                        <div>
                          <p className="text-xs text-muted-foreground">Clearance No.</p>
                          <p className="truncate font-mono text-xs">{clearance.number}</p>
                        </div>
                        <div>
                          <p className="text-xs text-muted-foreground">Issue Date</p>
                          <p className="text-sm">{clearance.issue_date}</p>
                        </div>
                        <div>
                          <p className="text-xs text-muted-foreground">Expiration</p>
                          <p className="text-sm">{clearance.expiration_date}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </WidgetBody>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <ScrollText className="size-4 text-muted-foreground" />
              Active Applications
            </CardTitle>
            <CardDescription>
              Select an application to view its live status
            </CardDescription>
          </CardHeader>
          <CardContent>
            <WidgetBody
              loading={loading}
              isError={isError}
              isEmpty={activeApplications.length === 0}
              emptyText="No active applications. Submit your first inspection request."
            >
              <div className="divide-y divide-border">
                {activeApplications.map((application) => {
                  const isSelected = selectedApplication?.id === application.id

                  return (
                    <button
                      key={application.id}
                      type="button"
                      onClick={() => setSelectedId(application.id)}
                      className={cn(
                        'flex w-full items-start gap-3 py-3 text-left first:pt-0 last:pb-0',
                        isSelected ? 'opacity-100' : 'opacity-70 hover:opacity-100',
                      )}
                    >
                      <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                        <FileText className="size-4 text-muted-foreground" />
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                          <p className="truncate text-sm font-medium">
                            {application.business_name ?? 'Unknown'}
                          </p>
                          <Badge variant="outline">{application.category}</Badge>
                        </div>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                          {application.request_number} &middot; submitted {application.submitted_at}
                          {application.inspector_name ? ` &middot; ${application.inspector_name}` : ''}
                        </p>
                      </div>
                      <Badge variant={statusVariant(application.status)}>
                        {statusLabels[application.status] ?? application.status}
                      </Badge>
                    </button>
                  )
                })}
              </div>
            </WidgetBody>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <UserCheck className="size-4 text-muted-foreground" />
              Application Status
            </CardTitle>
            <CardDescription>
              {selectedApplication
                ? `Live tracking for ${selectedApplication.business_name ?? selectedApplication.request_number}`
                : 'No application selected'}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="space-y-4 py-1">
                {[0, 1, 2].map((index) => (
                  <Skeleton key={index} className="h-10 w-full" />
                ))}
              </div>
            ) : isError ? (
              <p className="py-6 text-center text-sm text-muted-foreground">
                Dashboard data is unavailable. Please refresh or try again later.
              </p>
            ) : selectedFlow.length === 0 ? (
              <p className="py-6 text-center text-sm text-muted-foreground">
                Submit an inspection request to start tracking its status here.
              </p>
            ) : (
              <ol className="space-y-1">
                {selectedFlow.map((step, index) => {
                  const isLast = index === selectedFlow.length - 1

                  return (
                    <li key={step.key} className="flex gap-3">
                      <div className="flex flex-col items-center">
                        <div
                          className={cn(
                            'flex size-6 shrink-0 items-center justify-center rounded-full border-2',
                            step.done && 'border-accent bg-accent text-accent-foreground',
                            step.current && !step.done && 'border-accent text-accent',
                            !step.done && !step.current && 'border-border text-muted-foreground',
                          )}
                        >
                          {step.done ? (
                            <Check className="size-3.5" />
                          ) : step.current ? (
                            <span className="size-2 rounded-full bg-accent" />
                          ) : null}
                        </div>
                        {!isLast && <div className="w-px flex-1 bg-border" />}
                      </div>
                      <div className="pb-4">
                        <p
                          className={cn(
                            'text-sm',
                            step.current ? 'font-semibold text-foreground' : 'font-medium text-foreground/80',
                          )}
                        >
                          {step.label}
                        </p>
                        {step.current && (
                          <p className="mt-0.5 text-xs text-accent">
                            {statusLabels[selectedApplication.status] ?? 'Current status'}
                          </p>
                        )}
                      </div>
                    </li>
                  )
                })}
              </ol>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <History className="size-4 text-muted-foreground" />
              Inspection History
            </CardTitle>
            <CardDescription>Past inspections across your establishments</CardDescription>
          </CardHeader>
          <CardContent>
            <WidgetBody
              loading={loading}
              isError={isError}
              isEmpty={inspectionHistory.length === 0}
              emptyText="No inspections recorded for your establishments yet."
            >
              <div className="divide-y divide-border">
                {inspectionHistory.map((inspection) => (
                  <div key={inspection.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-accent/10">
                      <ClipboardCheck className="size-4 text-accent" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">{inspection.establishment_name}</p>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {inspection.inspector_name} &middot; {inspection.date}
                      </p>
                      {inspection.business_type && (
                        <p className="mt-0.5 text-xs text-muted-foreground">{inspection.business_type}</p>
                      )}
                    </div>
                    <Badge variant="outline">{inspection.status}</Badge>
                  </div>
                ))}
              </div>
            </WidgetBody>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Bell className="size-4 text-muted-foreground" />
            Notifications
            {unreadCount > 0 && <Badge variant="secondary">{unreadCount} unread</Badge>}
          </CardTitle>
          <CardDescription>Latest updates on your applications and clearances</CardDescription>
        </CardHeader>
        <CardContent>
          <WidgetBody
            loading={loading}
            isError={isError}
            isEmpty={notifications.length === 0}
            emptyText="No notifications yet."
          >
            <div className="divide-y divide-border">
              {notifications.map((notification) => {
                const isUnread = !notification.read_at

                return (
                  <Link
                    key={notification.id}
                    to="/resident/notifications"
                    className={cn(
                      'flex items-start gap-3 py-3 first:pt-0 last:pb-0',
                      isUnread ? 'opacity-100' : 'opacity-70 hover:opacity-100',
                    )}
                  >
                    <div
                      className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-full',
                        isUnread ? 'bg-accent/10 text-accent' : 'bg-muted text-muted-foreground',
                      )}
                    >
                      <Bell className="size-4" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className={cn('truncate text-sm', isUnread ? 'font-semibold' : 'font-medium')}>
                        {notification.data?.title ?? 'Notification'}
                      </p>
                      <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                        {notification.data?.message ?? ''}
                      </p>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-1">
                      <span className="text-xs text-muted-foreground">
                        {formatRelativeTime(notification.created_at)}
                      </span>
                      {isUnread && <span className="size-2 rounded-full bg-accent" />}
                    </div>
                  </Link>
                )
              })}
            </div>
            {notifications.length > 0 && (
              <Link
                to="/resident/notifications"
                className="mt-4 inline-flex items-center gap-1 text-sm text-accent hover:underline"
              >
                View all notifications
                <ArrowRight className="size-4" />
              </Link>
            )}
          </WidgetBody>
        </CardContent>
      </Card>
    </div>
  )
}
