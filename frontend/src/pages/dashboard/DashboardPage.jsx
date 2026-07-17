import { useEffect, useState } from 'react'
import {
  AlertTriangle,
  Building2,
  CalendarDays,
  ClipboardCheck,
} from 'lucide-react'
import { toast } from 'sonner'

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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Skeleton } from '@/components/ui/skeleton'

function statusVariant(status) {
  switch (status) {
    case 'Completed':
      return 'default'
    case 'Ongoing':
      return 'secondary'
    case 'Scheduled':
      return 'outline'
    default:
      return 'outline'
  }
}

export default function DashboardPage() {
  const [data, setData] = useState(null)
  const [error, setError] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    async function fetchDashboardData() {
      try {
        setError(false)
        const response = await api.get('/v1/dashboard')
        setData(response.data.data)
      } catch (error) {
        setError(true)
        toast.error('Failed to load dashboard metrics')
        console.error(error)
      } finally {
        setLoading(false)
      }
    }

    fetchDashboardData()
  }, [])

  const statsList = [
    {
      title: 'Scheduled Inspections',
      value: data?.stats?.scheduled_inspections,
      description: 'Upcoming scheduled checks',
      icon: CalendarDays,
    },
    {
      title: 'Active Establishments',
      value: data?.stats?.active_establishments,
      description: 'Registered in the system',
      icon: Building2,
    },
    {
      title: 'Open Violations',
      value: data?.stats?.open_violations,
      description: 'Requiring follow-up',
      icon: AlertTriangle,
    },
    {
      title: 'Completed Inspections',
      value: data?.stats?.completed_inspections,
      description: 'Year to date',
      icon: ClipboardCheck,
    },
  ]

  const recentInspections = data?.recent_inspections ?? []

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight">Dashboard</h2>
        <p className="text-sm text-muted-foreground">
          Overview of health and safety inspection activities
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {statsList.map((stat, idx) => {
          const Icon = stat.icon

          return (
            <Card key={idx}>
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
                      {error ? '--' : (stat.value ?? 0)}
                    </div>
                    <p className="text-xs text-muted-foreground">{stat.description}</p>
                  </>
                )}
              </CardContent>
            </Card>
          )
        })}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Recent Inspections</CardTitle>
          <CardDescription>
            Latest scheduled and completed inspection activities
          </CardDescription>
        </CardHeader>
        <CardContent>
          {error ? (
            <p className="text-sm text-muted-foreground text-center py-6">
              Dashboard data is unavailable. Please refresh or try again later.
            </p>
          ) : loading ? (
            <div className="space-y-4">
              <Skeleton className="h-8 w-full" />
              <Skeleton className="h-8 w-full" />
              <Skeleton className="h-8 w-full" />
            </div>
          ) : recentInspections.length === 0 ? (
            <p className="text-sm text-muted-foreground text-center py-6">
              No recent inspections recorded.
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Establishment</TableHead>
                  <TableHead>Inspector</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentInspections.map((inspection, idx) => (
                  <TableRow key={inspection.id ?? idx}>
                    <TableCell className="font-medium">
                      {inspection.establishment_name}
                    </TableCell>
                    <TableCell>{inspection.inspector_name}</TableCell>
                    <TableCell>{inspection.date}</TableCell>
                    <TableCell>
                      <Badge variant={statusVariant(inspection.status)}>
                        {inspection.status}
                      </Badge>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
