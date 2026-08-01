import { useState } from 'react'
import { Download } from 'lucide-react'
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

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  fetchInspectionReports,
  fetchViolationReports,
  fetchClearanceReports,
  fetchDashboardReport,
} from '@/services/reportService'

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const inspectionChartConfig = {
  total: { label: 'Total', color: 'var(--color-chart-1)' },
  completed: { label: 'Completed', color: 'var(--color-chart-2)' },
}

const clearanceChartConfig = {
  issued: { label: 'Issued', color: 'var(--color-chart-3)' },
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

const overviewChartConfig = {
  inspections: { label: 'Inspections', color: 'var(--color-chart-1)' },
  clearances: { label: 'Clearances', color: 'var(--color-chart-3)' },
}

const SEVERITY_COLORS = ['var(--color-chart-1)', 'var(--color-chart-2)', 'var(--color-chart-4)']
const STATUS_COLORS = ['var(--color-chart-2)', 'var(--color-chart-1)']

function StatCard({ title, value, description }) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="text-3xl font-bold">{value ?? 0}</div>
        {description && <p className="text-xs text-muted-foreground mt-1">{description}</p>}
      </CardContent>
    </Card>
  )
}

function buildMonthlySeries(...sources) {
  const buckets = Array.from({ length: 12 }, (_, i) => ({ month: MONTH_LABELS[i] }))
  for (const { rows, field, key } of sources) {
    for (const row of rows ?? []) {
      const bucket = buckets[row.month - 1]
      if (bucket) bucket[field] = row[key] ?? 0
    }
  }
  return buckets
}

export default function ReportsPage() {
  const [insParams] = useState({})
  const [violParams] = useState({})
  const [clearParams] = useState({})

  const { data: insData, isLoading: insLoading } = useQuery({
    queryKey: ['report-inspections', insParams],
    queryFn: () => fetchInspectionReports(insParams),
  })

  const { data: violData, isLoading: violLoading } = useQuery({
    queryKey: ['report-violations', violParams],
    queryFn: () => fetchViolationReports(violParams),
  })

  const { data: clearData, isLoading: clearLoading } = useQuery({
    queryKey: ['report-clearances', clearParams],
    queryFn: () => fetchClearanceReports(clearParams),
  })

  const { data: dashData, isLoading: dashLoading } = useQuery({
    queryKey: ['report-dashboard'],
    queryFn: () => fetchDashboardReport(),
  })

  function handleDownloadCSV(reportType) {
    toast.info(`${reportType} CSV export coming soon`)
  }

  const insStats = insData?.data ?? {}
  const violStats = violData?.data ?? {}
  const clearStats = clearData?.data ?? {}
  const dashStats = dashData?.data ?? {}

  const insMonthly = insStats.monthly ?? []
  const clearMonthly = clearStats.monthly ?? []

  const insCompletedSeries = buildMonthlySeries(
    { rows: insMonthly, field: 'total', key: 'total' },
    { rows: insMonthly, field: 'completed', key: 'completed' },
  )

  const clearanceSeries = buildMonthlySeries({ rows: clearMonthly, field: 'issued', key: 'issued' })

  const overviewSeries = buildMonthlySeries(
    { rows: insMonthly, field: 'inspections', key: 'total' },
    { rows: clearMonthly, field: 'clearances', key: 'issued' },
  )

  const severityData = [
    { key: 'minor', label: 'Minor', value: violStats.by_severity?.minor ?? 0 },
    { key: 'moderate', label: 'Moderate', value: violStats.by_severity?.moderate ?? 0 },
    { key: 'major', label: 'Major', value: violStats.by_severity?.major ?? 0 },
  ]

  const statusData = [
    { key: 'open', label: 'Open', value: violStats.by_status?.open ?? 0 },
    { key: 'resolved', label: 'Resolved', value: violStats.by_status?.resolved ?? 0 },
  ]

  const hasAnyData = (arr) => arr.some((row) => Object.values(row).some((v) => typeof v === 'number' && v > 0))

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight">Reports & Analytics</h2>
        <p className="text-sm text-muted-foreground">Generate and view reports across all modules</p>
      </div>

      <Tabs defaultValue="overview">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="inspections">Inspections</TabsTrigger>
          <TabsTrigger value="violations">Violations</TabsTrigger>
          <TabsTrigger value="clearances">Clearances</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="space-y-4 pt-4">
          {dashLoading ? (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <Skeleton className="h-24" /><Skeleton className="h-24" /><Skeleton className="h-24" /><Skeleton className="h-24" />
            </div>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <StatCard title="Inspections (YTD)" value={dashStats.completed_inspections_ytd ?? insStats.total_inspections ?? 0} description="This year" />
              <StatCard title="Active Violations" value={dashStats.active_violations ?? violStats.by_status?.open ?? 0} description="Needing attention" />
              <StatCard title="Expiring Clearances" value={dashStats.expiring_clearances ?? clearStats.expiring_soon ?? 0} description="Within 30 days" />
              <StatCard title="Pending Requests" value={dashStats.pending_requests ?? 0} description="Awaiting review" />
            </div>
          )}
          <Card>
            <CardHeader>
              <CardTitle>Monthly Activity</CardTitle>
              <CardDescription>Inspections and clearances issued per month this year</CardDescription>
            </CardHeader>
            <CardContent>
              {insLoading || clearLoading ? (
                <Skeleton className="h-72 w-full" />
              ) : hasAnyData(overviewSeries) ? (
                <ChartContainer config={overviewChartConfig} className="h-72 w-full">
                  <BarChart data={overviewSeries}>
                    <CartesianGrid vertical={false} />
                    <XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} />
                    <YAxis tickLine={false} axisLine={false} width={36} allowDecimals={false} />
                    <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="line" />} />
                    <ChartLegend content={<ChartLegendContent />} />
                    <Bar dataKey="inspections" fill="var(--color-inspections)" radius={4} />
                    <Bar dataKey="clearances" fill="var(--color-clearances)" radius={4} />
                  </BarChart>
                </ChartContainer>
              ) : (
                <p className="text-sm text-muted-foreground text-center py-4">No data yet.</p>
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Quick Actions</CardTitle>
              <CardDescription>Export reports or view detailed breakdowns</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-wrap gap-3">
              <Button variant="outline" onClick={() => handleDownloadCSV('Inspection')}><Download className="size-4" /> Export Inspections</Button>
              <Button variant="outline" onClick={() => handleDownloadCSV('Violation')}><Download className="size-4" /> Export Violations</Button>
              <Button variant="outline" onClick={() => handleDownloadCSV('Clearance')}><Download className="size-4" /> Export Clearances</Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="inspections" className="space-y-4 pt-4">
          {insLoading ? <Skeleton className="h-40 w-full" /> : (
            <>
              <div className="grid gap-4 sm:grid-cols-3">
                <StatCard title="Total" value={insStats.total_inspections ?? 0} />
                <StatCard title="Completed" value={insStats.total_completed ?? 0} />
                <StatCard title="Year" value={insStats.year} description="Reporting period" />
              </div>
              <Card>
                <CardHeader>
                  <CardTitle>Monthly Inspection Trends</CardTitle>
                  <CardDescription>Inspection activity by month</CardDescription>
                </CardHeader>
                <CardContent>
                  {hasAnyData(insCompletedSeries) ? (
                    <ChartContainer config={inspectionChartConfig} className="h-72 w-full">
                      <BarChart data={insCompletedSeries}>
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
                    <p className="text-sm text-muted-foreground text-center py-4">No data yet.</p>
                  )}
                </CardContent>
              </Card>
            </>
          )}
        </TabsContent>

        <TabsContent value="violations" className="space-y-4 pt-4">
          {violLoading ? <Skeleton className="h-40 w-full" /> : (
            <>
              <div className="grid gap-4 sm:grid-cols-3">
                <StatCard title="Total Violations" value={violStats.total ?? 0} />
                <StatCard title="Open" value={violStats.by_status?.open ?? 0} />
                <StatCard title="Resolved" value={violStats.by_status?.resolved ?? 0} />
              </div>
              <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                  <CardHeader>
                    <CardTitle>Violations by Severity</CardTitle>
                    <CardDescription>Distribution by severity level</CardDescription>
                  </CardHeader>
                  <CardContent>
                    {violStats.total ? (
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
                      <p className="text-sm text-muted-foreground text-center py-4">No data yet.</p>
                    )}
                  </CardContent>
                </Card>
                <Card>
                  <CardHeader>
                    <CardTitle>Violations by Status</CardTitle>
                    <CardDescription>Open vs resolved violations</CardDescription>
                  </CardHeader>
                  <CardContent>
                    {violStats.total ? (
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
                      <p className="text-sm text-muted-foreground text-center py-4">No data yet.</p>
                    )}
                  </CardContent>
                </Card>
              </div>
            </>
          )}
        </TabsContent>

        <TabsContent value="clearances" className="space-y-4 pt-4">
          {clearLoading ? <Skeleton className="h-40 w-full" /> : (
            <>
              <div className="grid gap-4 sm:grid-cols-3">
                <StatCard title="Total Issued" value={clearStats.total_issued ?? 0} />
                <StatCard title="Expired" value={clearStats.expired ?? 0} />
                <StatCard title="Expiring Soon" value={clearStats.expiring_soon ?? 0} description="Within 30 days" />
              </div>
              <Card>
                <CardHeader>
                  <CardTitle>Monthly Clearance Issuance</CardTitle>
                  <CardDescription>Clearances issued by month</CardDescription>
                </CardHeader>
                <CardContent>
                  {hasAnyData(clearanceSeries) ? (
                    <ChartContainer config={clearanceChartConfig} className="h-72 w-full">
                      <BarChart data={clearanceSeries}>
                        <CartesianGrid vertical={false} />
                        <XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} />
                        <YAxis tickLine={false} axisLine={false} width={36} allowDecimals={false} />
                        <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="line" />} />
                        <Bar dataKey="issued" fill="var(--color-issued)" radius={4} />
                      </BarChart>
                    </ChartContainer>
                  ) : (
                    <p className="text-sm text-muted-foreground text-center py-4">No data yet.</p>
                  )}
                </CardContent>
              </Card>
            </>
          )}
        </TabsContent>
      </Tabs>
    </div>
  )
}
