import { useEffect, useMemo, useState } from 'react'
import { Archive, Download, Search } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'

import { useAuth } from '@/context/AuthContext'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import {
  archiveAuditLogs,
  exportAuditLogs,
  fetchAuditLogFilters,
  fetchAuditLogs,
} from '@/services/auditLogService'

const EMPTY_FILTERS = { event: '', module: '', action: '', user_id: '', from: '', to: '' }

function formatDate(value) {
  if (!value) return ''
  return new Date(value).toLocaleString()
}

function formatEvent(event) {
  if (!event) return 'N/A'
  return event
    .replace(/\./g, ' ')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

function describeLog(log) {
  const parts = []

  const auditable = log.auditable_type
    ? `${log.auditable_type}${log.auditable_id ? ` #${log.auditable_id}` : ''}`
    : null
  if (auditable) parts.push(auditable)

  const oldValues = log.old_values ?? {}
  const newValues = log.new_values ?? {}
  const changes = Object.keys(newValues)
    .map((key) => {
      const before = oldValues[key]
      const after = newValues[key]
      return before === undefined ? `${key}: ${after}` : `${key}: ${before} → ${after}`
    })

  if (changes.length > 0) parts.push(changes.join(', '))

  if (parts.length === 0) {
    if (log.description) parts.push(log.description)
    else parts.push(log.event ?? '')
  }

  return parts.join(' · ')
}

export default function AuditLogsPage() {
  const { user } = useAuth()
  const isAdmin = user?.role?.slug === 'administrator'

  const [logs, setLogs] = useState([])
  const [searchInput, setSearchInput] = useState('')
  const [filters, setFilters] = useState(EMPTY_FILTERS)
  const [showArchived, setShowArchived] = useState(false)
  const [archiveDate, setArchiveDate] = useState('')
  const [archiving, setArchiving] = useState(false)

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['audit-logs', filters, showArchived, isAdmin],
    queryFn: () =>
      fetchAuditLogs(
        isAdmin
          ? {
              per_page: 100,
              ...filters,
              archived: showArchived ? 1 : undefined,
            }
          : { per_page: 100 },
      ),
  })

  const { data: filterData } = useQuery({
    queryKey: ['audit-log-filters'],
    queryFn: fetchAuditLogFilters,
    enabled: isAdmin,
  })

  const filterOptions = useMemo(() => {
    const raw = filterData?.data ?? {}
    return {
      modules: Array.isArray(raw.modules) ? raw.modules : [],
      actions: Array.isArray(raw.actions) ? raw.actions : [],
      users: Array.isArray(raw.users) ? raw.users : [],
    }
  }, [filterData])

  useEffect(() => { if (isError) toast.error('Unable to load audit logs') }, [isError])
  useEffect(() => {
    if (!data) return
    const raw = data.data?.logs ?? data.data
    setLogs(Array.isArray(raw) ? raw : [])
  }, [data])

  const filtered = useMemo(() => {
    const query = searchInput.trim().toLowerCase()
    if (!query) return logs

    return (logs ?? []).filter((log) =>
      formatEvent(log.event).toLowerCase().includes(query) ||
      describeLog(log).toLowerCase().includes(query) ||
      (log.module ?? '').toLowerCase().includes(query) ||
      (log.action ?? '').toLowerCase().includes(query) ||
      (log.user?.name ?? 'system').toLowerCase().includes(query) ||
      (log.ip_address ?? '').toLowerCase().includes(query)
    )
  }, [logs, searchInput])

  const setFilter = (key, value) => setFilters((prev) => ({ ...prev, [key]: value }))

  const downloadBlob = (blob, filename) => {
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  }

  const handleExport = async (format) => {
    try {
      const blob = await exportAuditLogs({ format, ...filters })
      const extension = format === 'excel' ? 'xlsx' : format
      downloadBlob(blob, `audit-logs-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.${extension}`)
      toast.success('Audit log export started')
    } catch {
      toast.error('Unable to export audit logs')
    }
  }

  const handleArchive = async () => {
    setArchiving(true)
    try {
      const response = await archiveAuditLogs({ before: archiveDate || undefined })
      const count = response?.data?.archived_count ?? 0
      toast.success(`${count} audit log(s) archived. Archived logs are preserved and never deleted.`)
      setArchiveDate('')
      if (count > 0) refetch()
    } catch {
      toast.error('Unable to archive audit logs')
    } finally {
      setArchiving(false)
    }
  }

  const hasFilters = Object.values(filters).some(Boolean)

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight">Audit Logs</h2>
        <p className="text-sm text-muted-foreground">Track all user activities and system changes</p>
      </div>

      {isAdmin && (
        <Card>
          <CardHeader>
            <CardTitle>Filters & Exports</CardTitle>
            <CardDescription>Narrow down activity, export reports, or archive old logs</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
              <div className="space-y-1.5">
                <Label htmlFor="filter-event">Event</Label>
                <Input id="filter-event" placeholder="e.g. auth" value={filters.event} onChange={(e) => setFilter('event', e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="filter-module">Module</Label>
                <select id="filter-module" className="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm" value={filters.module} onChange={(e) => setFilter('module', e.target.value)}>
                  <option value="">All modules</option>
                  {filterOptions.modules.map((module) => <option key={module} value={module}>{module}</option>)}
                </select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="filter-action">Action</Label>
                <select id="filter-action" className="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm" value={filters.action} onChange={(e) => setFilter('action', e.target.value)}>
                  <option value="">All actions</option>
                  {filterOptions.actions.map((action) => <option key={action} value={action}>{action}</option>)}
                </select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="filter-user">User</Label>
                <select id="filter-user" className="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm" value={filters.user_id} onChange={(e) => setFilter('user_id', e.target.value)}>
                  <option value="">All users</option>
                  {filterOptions.users.map((entry) => <option key={entry.id} value={entry.id}>{entry.name}{entry.role ? ` (${entry.role})` : ''}</option>)}
                </select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="filter-from">From</Label>
                <Input id="filter-from" type="date" value={filters.from} onChange={(e) => setFilter('from', e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="filter-to">To</Label>
                <Input id="filter-to" type="date" value={filters.to} onChange={(e) => setFilter('to', e.target.value)} />
              </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-4 border-t pt-4">
              <div className="flex items-center gap-2">
                <Button variant="outline" size="sm" onClick={() => handleExport('csv')} disabled={!hasFilters && logs.length === 0}>
                  <Download className="mr-1.5 size-4" /> CSV
                </Button>
                <Button variant="outline" size="sm" onClick={() => handleExport('excel')} disabled={!hasFilters && logs.length === 0}>
                  <Download className="mr-1.5 size-4" /> Excel
                </Button>
                <Button variant="outline" size="sm" onClick={() => handleExport('pdf')} disabled={!hasFilters && logs.length === 0}>
                  <Download className="mr-1.5 size-4" /> PDF
                </Button>
              </div>
              <div className="flex items-center gap-2">
                <Switch id="show-archived" checked={showArchived} onCheckedChange={setShowArchived} />
                <Label htmlFor="show-archived">Show archived</Label>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {isAdmin && (
        <Card>
          <CardHeader>
            <CardTitle>Archive Old Logs</CardTitle>
            <CardDescription>Move logs older than the cutoff date into the archive. Archived logs are preserved and never deleted.</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-wrap items-end gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="archive-date">Archive before</Label>
              <Input id="archive-date" type="date" value={archiveDate} onChange={(e) => setArchiveDate(e.target.value)} />
            </div>
            <Button onClick={handleArchive} disabled={archiving} variant="secondary">
              <Archive className="mr-1.5 size-4" />
              {archiving ? 'Archiving...' : 'Archive older logs'}
            </Button>
            <p className="text-xs text-muted-foreground">Leave the date empty to archive logs older than 30 days.</p>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Activity Log</CardTitle>
          <CardDescription>Login history, document access, and system changes</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="relative max-w-sm">
            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
            <Input className="pl-8" placeholder="Search activity..." value={searchInput} onChange={(e) => setSearchInput(e.target.value)} />
          </div>

          {isLoading ? (
            <div className="space-y-3"><Skeleton className="h-10 w-full" /><Skeleton className="h-10 w-full" /></div>
          ) : filtered.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">
              {showArchived ? 'No archived audit logs found.' : 'No audit logs found.'}
            </p>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>User</TableHead>
                    <TableHead>Action</TableHead>
                    <TableHead>Details</TableHead>
                    <TableHead>IP Address</TableHead>
                    <TableHead>Date & Time</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filtered.map((log) => (
                    <TableRow key={log.id}>
                      <TableCell className="font-medium">
                        {log.user?.name ?? 'System'}
                        {log.user?.role ? <span className="ml-1.5 text-xs text-muted-foreground">({log.user.role})</span> : null}
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">
                          {log.module && log.action ? `${log.module} · ${log.action}` : formatEvent(log.event)}
                        </Badge>
                      </TableCell>
                      <TableCell className="max-w-md truncate">{describeLog(log)}</TableCell>
                      <TableCell className="font-mono text-xs">{log.ip_address ?? 'N/A'}</TableCell>
                      <TableCell className="text-sm">{formatDate(log.created_at)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
