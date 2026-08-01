import { useEffect, useState } from 'react'
import { ClipboardCheck, FileUp, RefreshCw, Search } from 'lucide-react'
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
import { fetchFollowUps, requestFollowUp } from '@/services/followUpService'

const statusLabels = { pending: 'Pending', scheduled: 'Scheduled', completed: 'Completed' }
const statusVariant = (s) => s === 'completed' ? 'default' : s === 'scheduled' ? 'secondary' : 'outline'

function formatDate(value) {
  if (!value) return 'N/A'
  return new Date(value + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

export default function FollowUpPage() {
  const [followUps, setFollowUps] = useState([])
  const [dialogOpen, setDialogOpen] = useState(false)
  const [requestId, setRequestId] = useState('')
  const [notes, setNotes] = useState('')
  const [files, setFiles] = useState([])
  const [submitting, setSubmitting] = useState(false)

  const { data, isError, isLoading, refetch, isFetching } = useQuery({
    queryKey: ['follow-ups'],
    queryFn: () => fetchFollowUps(),
  })

  useEffect(() => {
    if (data) setFollowUps(data.data?.follow_ups ?? data.data ?? [])
  }, [data])

  useEffect(() => {
    if (isError) toast.error('Unable to load follow-ups')
  }, [isError])

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    try {
      const payload = new FormData()
      payload.append('inspection_request_id', requestId)
      payload.append('notes', notes)
      files.forEach((f) => payload.append('evidence_files[]', f))
      await requestFollowUp(payload)
      toast.success('Follow-up inspection requested')
      setDialogOpen(false)
      setRequestId(''); setNotes(''); setFiles([])
      await refetch()
    } catch (err) {
      toast.error(err.response?.data?.message ?? 'Unable to submit follow-up request')
    } finally { setSubmitting(false) }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold tracking-tight">Follow-Up Inspections</h2>
          <p className="text-sm text-muted-foreground">Request follow-up inspections after addressing violations</p>
        </div>
        <Button onClick={() => setDialogOpen(true)}>
          <RefreshCw className="size-4" />
          Request Follow-Up
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Follow-Up Requests</CardTitle>
          <CardDescription>Track your follow-up inspection status</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-3"><Skeleton className="h-10 w-full" /><Skeleton className="h-10 w-full" /></div>
          ) : followUps.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">No follow-up requests found.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Request</TableHead>
                  <TableHead>Date Requested</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Notes</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {followUps.map((fu) => (
                  <TableRow key={fu.id}>
                    <TableCell className="font-medium">{fu.inspection_request?.business_name ?? `#${fu.inspection_request_id}`}</TableCell>
                    <TableCell>{formatDate(fu.created_at)}</TableCell>
                    <TableCell><Badge variant={statusVariant(fu.status)}>{statusLabels[fu.status] ?? fu.status}</Badge></TableCell>
                    <TableCell className="text-sm text-muted-foreground max-w-xs truncate">{fu.notes ?? 'N/A'}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle>Request Follow-Up Inspection</DialogTitle>
            <DialogDescription>Upload evidence of corrective actions taken</DialogDescription>
          </DialogHeader>
          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="space-y-2">
              <Label>Inspection Request ID</Label>
              <Input value={requestId} onChange={(e) => setRequestId(e.target.value)} placeholder="Enter request ID" required />
            </div>
            <div className="space-y-2">
              <Label>Notes / Description of corrective actions</Label>
              <textarea className="min-h-20 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                value={notes} onChange={(e) => setNotes(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>Upload Compliance Evidence</Label>
              <div className="flex items-center gap-2">
                <Input type="file" multiple accept="image/*,.pdf" onChange={(e) => setFiles(Array.from(e.target.files ?? []))} />
                <FileUp className="size-4 text-muted-foreground" />
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={submitting}>{submitting ? 'Submitting...' : 'Submit Request'}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
