import { useEffect, useMemo, useState } from 'react'
import {
  AlertTriangle,
  CalendarDays,
  CalendarPlus,
  ChevronLeft,
  ChevronRight,
  Loader2,
  RefreshCw,
} from 'lucide-react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import {
  fetchCalendar,
  fetchInspectorAvailability,
  scheduleInspection,
} from '@/services/calendarService'
import { fetchInspectionOptions, createInspectionSchedule } from '@/services/inspectionService'
import { fetchInspectionQueue } from '@/services/inspectionRequestService'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
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
import { cn } from '@/lib/utils'

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]
const TIME_SLOTS = Array.from({ length: 12 }, (_, index) => index + 7)

const COLOR_PALETTE = [
  'bg-blue-500/85 text-white hover:bg-blue-500',
  'bg-emerald-500/85 text-white hover:bg-emerald-500',
  'bg-amber-500/85 text-white hover:bg-amber-500',
  'bg-violet-500/85 text-white hover:bg-violet-500',
  'bg-rose-500/85 text-white hover:bg-rose-500',
  'bg-cyan-600/85 text-white hover:bg-cyan-600',
  'bg-lime-600/85 text-white hover:bg-lime-600',
  'bg-fuchsia-600/85 text-white hover:bg-fuchsia-600',
]

const STATUS_OPTIONS = [
  { value: 'all', label: 'All Statuses' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'completed', label: 'Completed' },
  { value: 'follow_up', label: 'Follow-up Scheduled' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'ongoing', label: 'Ongoing' },
  { value: 'cancelled', label: 'Cancelled' },
]

function pad(value) {
  return String(value).padStart(2, '0')
}

function toISODate(date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function startOfWeek(date) {
  const result = new Date(date)
  const day = (result.getDay() + 6) % 7
  result.setDate(result.getDate() - day)
  result.setHours(0, 0, 0, 0)

  return result
}

function toTime24(value) {
  if (!value) {
    return '09:00'
  }

  return String(value).slice(0, 5)
}

export default function CalendarPage() {
  const queryClient = useQueryClient()

  const [view, setView] = useState('month')
  const [anchor, setAnchor] = useState(() => {
    const now = new Date()
    return new Date(now.getFullYear(), now.getMonth(), now.getDate())
  })
  const [inspectorFilter, setInspectorFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [colorBy, setColorBy] = useState('category')
  const [draggedEvent, setDraggedEvent] = useState(null)

  const [assignOpen, setAssignOpen] = useState(false)
  const [assignForm, setAssignForm] = useState({ request_id: '', inspector_id: 'all', date: '', time: '09:00' })
  const [editOpen, setEditOpen] = useState(false)
  const [editForm, setEditForm] = useState({ id: null, date: '', time: '', inspector_id: '' })

  const optionsQuery = useQuery({
    queryKey: ['calendar-options'],
    queryFn: () => fetchInspectionOptions().then((body) => body.data),
  })

  const inspectors = optionsQuery.data?.inspectors ?? []

  const { from, to } = useMemo(() => {
    if (view === 'month') {
      const start = new Date(anchor.getFullYear(), anchor.getMonth(), 1)
      const end = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 0)

      return { from: toISODate(start), to: toISODate(end) }
    }

    if (view === 'week') {
      const start = startOfWeek(anchor)
      const end = new Date(start)
      end.setDate(start.getDate() + 6)

      return { from: toISODate(start), to: toISODate(end) }
    }

    return { from: toISODate(anchor), to: toISODate(anchor) }
  }, [anchor, view])

  const calendarQuery = useQuery({
    queryKey: ['calendar', from, to, inspectorFilter, statusFilter],
    queryFn: () =>
      fetchCalendar({
        from,
        to,
        inspector_id: inspectorFilter === 'all' ? undefined : inspectorFilter,
        status: statusFilter,
      }).then((body) => body.data),
  })

  const events = useMemo(() => calendarQuery.data?.events ?? [], [calendarQuery.data])
  const conflictEvents = events.filter((event) => event.conflict)

  useEffect(() => {
    if (calendarQuery.isError) {
      toast.error('Failed to load calendar')
    }
  }, [calendarQuery.error, calendarQuery.isError])

  const colorKeys = useMemo(() => {
    const seen = []
    for (const event of events) {
      const key = colorBy === 'category' ? event.category : String(event.inspector_id)
      if (!seen.includes(key)) {
        seen.push(key)
      }
    }

    return seen
  }, [events, colorBy])

  const colorIndex = useMemo(() => {
    const map = {}
    colorKeys.forEach((key, index) => {
      map[key] = index % COLOR_PALETTE.length
    })

    return map
  }, [colorKeys])

  const colorFor = (event) => {
    const key = colorBy === 'category' ? event.category : String(event.inspector_id)
    return COLOR_PALETTE[colorIndex[key] ?? 0]
  }

  const eventsByDate = useMemo(() => {
    const map = {}

    for (const event of events) {
      const key = event.scheduled_date
      map[key] = map[key] ?? []
      map[key].push(event)
    }

    for (const key of Object.keys(map)) {
      map[key].sort((a, b) => toTime24(a.scheduled_time).localeCompare(toTime24(b.scheduled_time)))
    }

    return map
  }, [events])

  const monthCells = useMemo(() => {
    const year = anchor.getFullYear()
    const month = anchor.getMonth()
    const first = new Date(year, month, 1)
    const daysInMonth = new Date(year, month + 1, 0).getDate()
    const leading = (first.getDay() + 6) % 7
    const cells = []

    for (let index = 0; index < leading; index += 1) {
      cells.push(null)
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
      cells.push(toISODate(new Date(year, month, day)))
    }

    while (cells.length % 7 !== 0) {
      cells.push(null)
    }

    return cells
  }, [anchor])

  const weekDays = useMemo(() => {
    const start = view === 'week' ? startOfWeek(anchor) : anchor

    return Array.from({ length: view === 'week' ? 7 : 1 }, (_, index) => {
      const date = new Date(start)
      date.setDate(start.getDate() + index)

      return date
    })
  }, [anchor, view])

  const queueQuery = useQuery({
    queryKey: ['calendar-assign-queue'],
    queryFn: () => fetchInspectionQueue({ per_page: 50 }).then((body) => body.data?.queue ?? []),
    enabled: assignOpen,
  })

  const assignableRequests = queueQuery.data ?? []

  const invalidateCalendar = () => {
    queryClient.invalidateQueries({ queryKey: ['calendar'] })
  }

  const createMutation = useMutation({
    mutationFn: (payload) => createInspectionSchedule(payload),
    onSuccess: () => {
      toast.success('Inspection scheduled')
      setAssignOpen(false)
      invalidateCalendar()
    },
    onError: () => toast.error('Failed to schedule inspection'),
  })

  const rescheduleMutation = useMutation({
    mutationFn: ({ id, payload }) => scheduleInspection(id, payload),
    onSuccess: (body) => {
      const conflicts = body.data?.conflicts ?? []

      if (conflicts.length > 0) {
        toast.warning(
          `Scheduled with a conflict: inspector already has ${conflicts.length} overlapping inspection(s).`,
          { duration: 6000 },
        )
      } else {
        toast.success('Inspection rescheduled')
      }

      setEditOpen(false)
      invalidateCalendar()
    },
    onError: (error) => {
      const status = error.response?.status

      if (status === 409) {
        toast.error('This schedule was modified by someone else. Refreshing...')
        invalidateCalendar()
      } else {
        toast.error('Failed to reschedule inspection')
      }
    },
  })

  const availabilityQuery = useQuery({
    queryKey: ['inspector-availability', editForm.inspector_id || assignForm.inspector_id, editForm.date || assignForm.date],
    queryFn: () =>
      fetchInspectorAvailability(editForm.inspector_id || assignForm.inspector_id, {
        from: editForm.date || assignForm.date,
        to: editForm.date || assignForm.date,
      }).then((body) => body.data),
    enabled:
      (editOpen || assignOpen) &&
      Boolean(editForm.inspector_id || assignForm.inspector_id !== 'all') &&
      Boolean(editForm.date || assignForm.date),
  })

  const availabilityBookings = availabilityQuery.data?.bookings ?? []

  const shift = (amount) => {
    const next = new Date(anchor)

    if (view === 'month') {
      next.setMonth(next.getMonth() + amount)
    } else {
      next.setDate(next.getDate() + amount * (view === 'week' ? 7 : 1))
    }

    setAnchor(next)
  }

  const goToday = () => {
    const now = new Date()
    setAnchor(new Date(now.getFullYear(), now.getMonth(), now.getDate()))
  }

  const openAssign = (date, time) => {
    setAssignForm({ request_id: '', inspector_id: inspectorFilter === 'all' ? 'all' : inspectorFilter, date, time: time ?? '09:00' })
    setAssignOpen(true)
  }

  const openEdit = (event) => {
    setEditForm({
      id: event.id,
      date: event.scheduled_date,
      time: toTime24(event.scheduled_time),
      inspector_id: String(event.inspector_id),
    })
    setEditOpen(true)
  }

  const handleDrop = (date, time) => {
    if (!draggedEvent) {
      return
    }

    const event = events.find((item) => item.id === draggedEvent)
    setDraggedEvent(null)

    if (!event) {
      return
    }

    rescheduleMutation.mutate({
      id: event.id,
      payload: {
        scheduled_date: date,
        scheduled_time: time ?? toTime24(event.scheduled_time),
        expected_version: event.server_version,
      },
    })
  }

  const submitAssign = () => {
    const request = assignableRequests.find((item) => String(item.id) === assignForm.request_id)

    if (!request) {
      toast.error('Please select an inspection request')
      return
    }

    createMutation.mutate({
      establishment_id: request.establishment?.id,
      inspector_id: assignForm.inspector_id,
      scheduled_date: assignForm.date,
      scheduled_time: assignForm.time || null,
      status: 'scheduled',
      inspection_request_id: request.id,
    })
  }

  const submitEdit = () => {
    rescheduleMutation.mutate({
      id: editForm.id,
      payload: {
        scheduled_date: editForm.date,
        scheduled_time: editForm.time || null,
        inspector_id: editForm.inspector_id,
      },
    })
  }

  const viewLabel =
    view === 'month'
      ? `${MONTHS[anchor.getMonth()]} ${anchor.getFullYear()}`
      : view === 'week'
        ? `Week of ${toISODate(weekDays[0])}`
        : toISODate(anchor)

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-2xl font-semibold tracking-tight">Scheduling Calendar</h2>
            {calendarQuery.isFetching && (
              <span className="text-xs text-muted-foreground">Refreshing...</span>
            )}
          </div>
          <p className="text-sm text-muted-foreground">
            Assign inspection dates and times to inspectors, with live conflict warnings
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={invalidateCalendar}>
          <RefreshCw className="size-4" />
          Refresh
        </Button>
      </div>

      <Card>
        <CardHeader className="pb-3">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-1">
              <Button variant="outline" size="icon" onClick={() => shift(-1)}>
                <ChevronLeft className="size-4" />
              </Button>
              <Button variant="outline" size="sm" onClick={goToday}>
                Today
              </Button>
              <Button variant="outline" size="icon" onClick={() => shift(1)}>
                <ChevronRight className="size-4" />
              </Button>
              <h3 className="ml-2 text-lg font-semibold">{viewLabel}</h3>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <div className="inline-flex items-center rounded-lg border border-input bg-background p-0.5">
                {['month', 'week', 'day'].map((option) => (
                  <button
                    key={option}
                    type="button"
                    onClick={() => setView(option)}
                    className={cn(
                      'rounded-md px-3 py-1 text-sm font-medium capitalize transition-colors',
                      view === option
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:text-foreground',
                    )}
                  >
                    {option}
                  </button>
                ))}
              </div>
              <select
                className="h-9 rounded-lg border border-input bg-background px-2.5 text-sm"
                value={inspectorFilter}
                onChange={(e) => setInspectorFilter(e.target.value)}
              >
                <option value="all">All Inspectors</option>
                {inspectors.map((inspector) => (
                  <option key={inspector.id} value={inspector.id}>
                    {inspector.name}
                  </option>
                ))}
              </select>
              <select
                className="h-9 rounded-lg border border-input bg-background px-2.5 text-sm"
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
              >
                {STATUS_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              <select
                className="h-9 rounded-lg border border-input bg-background px-2.5 text-sm"
                value={colorBy}
                onChange={(e) => setColorBy(e.target.value)}
              >
                <option value="category">Color by Category</option>
                <option value="inspector">Color by Inspector</option>
              </select>
            </div>
          </div>

          {colorKeys.length > 0 && (
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <span className="text-xs text-muted-foreground">Legend:</span>
              {colorKeys.map((key) => (
                <span key={key} className="inline-flex items-center gap-1.5 text-xs">
                  <span className={cn('size-2.5 rounded-full', COLOR_PALETTE[colorIndex[key] ?? 0])} />
                  {key}
                </span>
              ))}
            </div>
          )}

          {conflictEvents.length > 0 && (
            <div className="mt-2 flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-2.5 text-sm">
              <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-500" />
              <p className="text-amber-700 dark:text-amber-300">
                {conflictEvents.length} inspection(s) overlap with another booking for the same
                inspector. Consider rescheduling to avoid double-bookings.
              </p>
            </div>
          )}
        </CardHeader>

        <CardContent>
          {calendarQuery.isLoading ? (
            <div className="space-y-2">
              <Skeleton className="h-72 w-full rounded-xl" />
            </div>
          ) : (
            <>
              {view === 'month' ? (
                <div className="overflow-hidden rounded-xl border border-border">
                  <div className="grid grid-cols-7 border-b border-border bg-muted/40">
                    {WEEKDAYS.map((day) => (
                      <div key={day} className="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        {day}
                      </div>
                    ))}
                  </div>
                  <div className="grid grid-cols-7">
                    {monthCells.map((date, index) =>
                      date === null ? (
                        <div key={`blank-${index}`} className="min-h-24 border-b border-r border-border/60 bg-muted/10 last:border-r-0" />
                      ) : (
                        <div
                          key={date}
                          onClick={() => openAssign(date)}
                          onDragOver={(e) => e.preventDefault()}
                          onDrop={(e) => {
                            e.preventDefault()
                            handleDrop(date)
                          }}
                          className={cn(
                            'min-h-24 cursor-pointer border-b border-r border-border/60 p-1.5 transition-colors last:border-r-0 hover:bg-accent/40',
                            date === toISODate(new Date()) && 'bg-primary/5',
                          )}
                        >
                          <div className="mb-1 flex items-center justify-between">
                            <span
                              className={cn(
                                'flex size-6 items-center justify-center rounded-full text-xs font-medium',
                                date === toISODate(new Date()) && 'bg-primary text-primary-foreground',
                              )}
                            >
                              {Number(date.slice(8))}
                            </span>
                            <CalendarPlus className="size-3.5 text-muted-foreground/60" />
                          </div>
                          <div className="space-y-1">
                            {(eventsByDate[date] ?? []).slice(0, 3).map((event) => (
                              <EventChip
                                key={event.id}
                                event={event}
                                className={colorFor(event)}
                                onClick={(e) => {
                                  e.stopPropagation()
                                  openEdit(event)
                                }}
                                onDragStart={() => setDraggedEvent(event.id)}
                              />
                            ))}
                            {(eventsByDate[date] ?? []).length > 3 && (
                              <p className="px-1 text-[0.65rem] text-muted-foreground">
                                +{(eventsByDate[date] ?? []).length - 3} more
                              </p>
                            )}
                          </div>
                        </div>
                      ),
                    )}
                  </div>
                </div>
              ) : (
                <div
                  className="overflow-x-auto rounded-xl border border-border"
                  style={{ gridTemplateColumns: `64px repeat(${weekDays.length}, minmax(120px, 1fr))` }}
                >
                  <div
                    className="grid min-w-max border-b border-border bg-muted/40"
                    style={{ gridTemplateColumns: `64px repeat(${weekDays.length}, minmax(120px, 1fr))` }}
                  >
                    <div className="px-2 py-2" />
                    {weekDays.map((date, index) => {
                      const isToday = toISODate(date) === toISODate(new Date())

                      return (
                        <div
                          key={index}
                          className={cn(
                            'px-2 py-2 text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground',
                            isToday && 'text-primary',
                          )}
                        >
                          {view === 'week' ? `${WEEKDAYS[index]} ` : ''}
                          {date.getDate()}
                        </div>
                      )
                    })}
                  </div>

                  <div className="grid min-w-max" style={{ gridTemplateColumns: `64px repeat(${weekDays.length}, minmax(120px, 1fr))` }}>
                    {TIME_SLOTS.map((hour) => (
                      <TimeRow
                        key={hour}
                        hour={hour}
                        days={weekDays}
                        eventsByDate={eventsByDate}
                        colorFor={colorFor}
                        onSlotClick={openAssign}
                        onDrop={handleDrop}
                        setDraggedEvent={setDraggedEvent}
                        openEdit={openEdit}
                      />
                    ))}
                  </div>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>

      <p className="text-center text-xs text-muted-foreground">
        Tip: click an empty slot to assign a pending request, or drag an inspection to a new slot to
        reschedule it.
      </p>

      <Dialog open={assignOpen} onOpenChange={setAssignOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Assign Inspection Request</DialogTitle>
            <DialogDescription>
              Choose a pending request and assign it to this time slot.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label className="text-xs text-muted-foreground">Inspection Request</Label>
              {queueQuery.isLoading ? (
                <Skeleton className="mt-1 h-9 w-full" />
              ) : (
                <select
                  className="mt-1 h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                  value={assignForm.request_id}
                  onChange={(e) => setAssignForm((prev) => ({ ...prev, request_id: e.target.value }))}
                >
                  <option value="">Select a request...</option>
                  {assignableRequests.map((request) => (
                    <option key={request.id} value={request.id}>
                      {request.request_number} &middot; {request.business_name || request.applicant_name}{' '}
                      ({request.status})
                    </option>
                  ))}
                </select>
              )}
            </div>
            <div>
              <Label className="text-xs text-muted-foreground">Inspector</Label>
              <select
                className="mt-1 h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                value={assignForm.inspector_id}
                onChange={(e) => setAssignForm((prev) => ({ ...prev, inspector_id: e.target.value }))}
              >
                <option value="all">Select an inspector...</option>
                {inspectors.map((inspector) => (
                  <option key={inspector.id} value={inspector.id}>
                    {inspector.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-xs text-muted-foreground">Date</Label>
                <Input
                  type="date"
                  className="mt-1"
                  value={assignForm.date}
                  onChange={(e) => setAssignForm((prev) => ({ ...prev, date: e.target.value }))}
                />
              </div>
              <div>
                <Label className="text-xs text-muted-foreground">Time</Label>
                <Input
                  type="time"
                  className="mt-1"
                  value={assignForm.time}
                  onChange={(e) => setAssignForm((prev) => ({ ...prev, time: e.target.value }))}
                />
              </div>
            </div>
            {availabilityQuery.isSuccess && assignForm.inspector_id !== 'all' && (
              <div>
                <Label className="text-xs text-muted-foreground">Inspector bookings on this date</Label>
                {availabilityBookings.length === 0 ? (
                  <p className="mt-1 text-sm text-emerald-600 dark:text-emerald-400">No existing bookings.</p>
                ) : (
                  <div className="mt-1 space-y-1">
                    {availabilityBookings.map((booking) => (
                      <div key={booking.id} className="flex items-center justify-between rounded-md border border-amber-500/40 bg-amber-500/10 px-2.5 py-1.5 text-xs">
                        <span className="font-medium">{booking.title}</span>
                        <span className="text-muted-foreground">
                          {new Date(booking.scheduled_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAssignOpen(false)}>
              Cancel
            </Button>
            <Button
              onClick={submitAssign}
              disabled={!assignForm.request_id || assignForm.inspector_id === 'all' || createMutation.isPending}
            >
              {createMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : <CalendarPlus className="size-4" />}
              Schedule Inspection
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reschedule Inspection</DialogTitle>
            <DialogDescription>
              Change the date, time, or reassign the inspector. Both the resident and inspector are
              notified automatically.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-xs text-muted-foreground">Date</Label>
                <Input
                  type="date"
                  className="mt-1"
                  value={editForm.date}
                  onChange={(e) => setEditForm((prev) => ({ ...prev, date: e.target.value }))}
                />
              </div>
              <div>
                <Label className="text-xs text-muted-foreground">Time</Label>
                <Input
                  type="time"
                  className="mt-1"
                  value={editForm.time}
                  onChange={(e) => setEditForm((prev) => ({ ...prev, time: e.target.value }))}
                />
              </div>
            </div>
            <div>
              <Label className="text-xs text-muted-foreground">Inspector</Label>
              <select
                className="mt-1 h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm"
                value={editForm.inspector_id}
                onChange={(e) => setEditForm((prev) => ({ ...prev, inspector_id: e.target.value }))}
              >
                {inspectors.map((inspector) => (
                  <option key={inspector.id} value={inspector.id}>
                    {inspector.name}
                  </option>
                ))}
              </select>
            </div>
            {availabilityQuery.isSuccess && (
              <div>
                <Label className="text-xs text-muted-foreground">Inspector bookings on this date</Label>
                {availabilityBookings.length === 0 ? (
                  <p className="mt-1 text-sm text-emerald-600 dark:text-emerald-400">No existing bookings.</p>
                ) : (
                  <div className="mt-1 space-y-1">
                    {availabilityBookings.map((booking) => (
                      <div key={booking.id} className="flex items-center justify-between rounded-md border border-amber-500/40 bg-amber-500/10 px-2.5 py-1.5 text-xs">
                        <span className="font-medium">{booking.title}</span>
                        <span className="text-muted-foreground">
                          {new Date(booking.scheduled_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditOpen(false)}>
              Cancel
            </Button>
            <Button
              onClick={submitEdit}
              disabled={!editForm.date || !editForm.inspector_id || rescheduleMutation.isPending}
            >
              {rescheduleMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : <CalendarDays className="size-4" />}
              Save Changes
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function EventChip({ event, className, onClick, onDragStart }) {
  return (
    <button
      type="button"
      draggable
      onDragStart={(e) => {
        e.dataTransfer.effectAllowed = 'move'
        onDragStart()
      }}
      onClick={onClick}
      className={cn(
        'flex w-full items-center gap-1 truncate rounded px-1.5 py-0.5 text-left text-[0.7rem] font-medium shadow-sm',
        className,
      )}
    >
      <span className="shrink-0">{toTime24(event.scheduled_time)}</span>
      <span className="truncate">{event.title}</span>
      {event.conflict && <AlertTriangle className="ml-auto size-3 shrink-0" />}
    </button>
  )
}

function TimeRow({ hour, days, eventsByDate, colorFor, onSlotClick, onDrop, setDraggedEvent, openEdit }) {
  return (
    <>
      <div className="flex items-start justify-end border-r border-b border-border/60 px-2 py-2 text-[0.65rem] tabular-nums text-muted-foreground">
        {pad(hour)}:00
      </div>
      {days.map((date, index) => {
        const dateKey = toISODate(date)
        const dayEvents = eventsByDate[dateKey] ?? []
        const hourEvents = dayEvents.filter((event) => Number(toTime24(event.scheduled_time).slice(0, 2)) === hour)

        return (
          <div
            key={index}
            className="min-h-12 border-r border-b border-border/60 p-1 last:border-r-0"
            onClick={() => onSlotClick(dateKey, `${pad(hour)}:00`)}
            onDragOver={(e) => e.preventDefault()}
            onDrop={(e) => {
              e.preventDefault()
              onDrop(dateKey, `${pad(hour)}:00`)
            }}
          >
            <div className="space-y-1">
              {hourEvents.map((event) => (
                <EventChip
                  key={event.id}
                  event={event}
                  className={colorFor(event)}
                  onClick={(e) => {
                    e.stopPropagation()
                    openEdit(event)
                  }}
                  onDragStart={() => setDraggedEvent(event.id)}
                />
              ))}
            </div>
          </div>
        )
      })}
    </>
  )
}
